<?php

namespace JonesRussell\XSuite\Jobs;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Models\XPost;
use JonesRussell\XSuite\Services\XApiErrorParser;
use JonesRussell\XSuite\Services\XApiService;

class PublishXPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 120;

    public function __construct(
        public XPost $xPost
    ) {}

    public function handle(): void
    {
        if (! $this->xPost->canPublish()) {
            Log::warning('XPost cannot be published', [
                'x_post_id' => $this->xPost->id,
                'status' => $this->xPost->status,
            ]);

            return;
        }

        try {
            $threadContent = $this->xPost->getFullThreadContent();

            $mediaIds = [];
            if ($this->xPost->hasMedia()) {
                $mediaIds = $this->uploadMedia();
            }

            $tweetId = $this->publishThread($threadContent, $mediaIds);

            $this->xPost->markAsPublished($tweetId);

            Log::info('XPost published successfully', [
                'x_post_id' => $this->xPost->id,
                'tweet_id' => $tweetId,
                'has_thread' => $this->xPost->hasThread(),
                'has_media' => $this->xPost->hasMedia(),
            ]);
        } catch (\Throwable $e) {
            $errorMessage = XApiErrorParser::getFriendlyMessage($e);

            Log::error('Failed to publish XPost', [
                'x_post_id' => $this->xPost->id,
                'error' => $errorMessage,
                'original_error' => $e->getPrevious()?->getMessage() ?? $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->xPost->markAsFailed($errorMessage);
            }

            throw $e;
        }
    }

    /**
     * @return array<string>
     *
     * @throws Exception
     */
    protected function uploadMedia(): array
    {
        $mediaIds = [];
        $xApiService = app(XApiService::class);

        foreach ($this->xPost->media_urls as $mediaPath) {
            try {
                $mediaId = $xApiService->uploadMedia($mediaPath);
                $mediaIds[] = $mediaId;
            } catch (Exception $e) {
                Log::warning('Failed to upload media for XPost', [
                    'x_post_id' => $this->xPost->id,
                    'media_path' => $mediaPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mediaIds;
    }

    /**
     * @param  array<string>  $threadContent
     * @param  array<string>  $mediaIds
     *
     * @throws Exception
     */
    protected function publishThread(array $threadContent, array $mediaIds = []): string
    {
        $firstTweetId = null;
        $previousTweetId = null;
        $xApiService = app(XApiService::class);

        foreach ($threadContent as $index => $content) {
            $tweetMediaIds = ($index === 0 && ! empty($mediaIds)) ? $mediaIds : null;

            $tweetResponse = $xApiService->postTweetAsDto($content, $tweetMediaIds, $previousTweetId);
            $tweetId = $tweetResponse->id;

            if ($firstTweetId === null) {
                $firstTweetId = $tweetId;
            }

            $previousTweetId = $tweetId;

            if (count($threadContent) > 1 && $index < count($threadContent) - 1) {
                sleep(1);
            }
        }

        return $firstTweetId;
    }

    public function failed(\Throwable $exception): void
    {
        $errorMessage = XApiErrorParser::getFriendlyMessage($exception);

        Log::error('XPost job failed after all retries', [
            'x_post_id' => $this->xPost->id,
            'error' => $errorMessage,
            'original_error' => $exception->getMessage(),
        ]);

        $this->xPost->markAsFailed($errorMessage);
    }
}
