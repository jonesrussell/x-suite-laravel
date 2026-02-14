<?php

declare(strict_types=1);

namespace JonesRussell\XSuite\Services;

use Atymic\Twitter\Facade\Twitter;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Data\X\XMentionsResponse;
use JonesRussell\XSuite\Data\X\XPostTweetResponse;
use JonesRussell\XSuite\Data\X\XSearchResponse;
use JonesRussell\XSuite\Data\X\XTweetData;
use JonesRussell\XSuite\Data\X\XUserData;

class XApiService
{
    protected function getOAuth2AccessToken(): ?string
    {
        $oauth2Token = DB::table('x_oauth2_tokens')
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($oauth2Token && $oauth2Token->access_token) {
            return $oauth2Token->access_token;
        }

        $oauth2AccessToken = env('TWITTER_OAUTH2_ACCESS_TOKEN');
        if ($oauth2AccessToken) {
            return $oauth2AccessToken;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function postTweet(string $text, ?array $mediaIds = null, ?string $replyToTweetId = null): array
    {
        $dto = $this->postTweetAsDto($text, $mediaIds, $replyToTweetId);

        return $dto->toArray();
    }

    /**
     * @throws Exception
     */
    public function postTweetAsDto(string $text, ?array $mediaIds = null, ?string $replyToTweetId = null): XPostTweetResponse
    {
        try {
            if (empty($text)) {
                throw new Exception('Tweet text cannot be empty');
            }

            $accessToken = $this->getOAuth2AccessToken();

            if (! $accessToken) {
                throw new Exception('OAuth 2.0 User Context access token required for posting tweets. Please complete the OAuth 2.0 flow at /x-oauth2/redirect');
            }

            $data = $this->postTweetWithOAuth2($text, $mediaIds, $replyToTweetId, $accessToken);

            return XPostTweetResponse::fromApiResponse($data);
        } catch (\Throwable $e) {
            $friendlyMessage = XApiErrorParser::getFriendlyMessage($e);

            Log::error('X API: Failed to post tweet', [
                'error' => $e->getMessage(),
                'friendly_message' => $friendlyMessage,
                'text_length' => strlen($text),
                'has_media' => ! empty($mediaIds),
                'reply_to' => $replyToTweetId,
            ]);

            $exception = $e instanceof Exception ? $e : new Exception($e->getMessage(), $e->getCode(), $e);
            throw new Exception($friendlyMessage, $exception->getCode(), $exception);
        }
    }

    /**
     * @throws Exception
     */
    public function uploadMedia(string $mediaPath): string
    {
        try {
            $accessToken = $this->getOAuth2AccessToken();

            if (! $accessToken) {
                throw new Exception('OAuth 2.0 User Context access token required for media upload. Please complete the OAuth 2.0 flow at /x-oauth2/redirect');
            }

            if (filter_var($mediaPath, FILTER_VALIDATE_URL)) {
                $fileContent = file_get_contents($mediaPath);
                $tempPath = tempnam(sys_get_temp_dir(), 'xpost_media_');
                file_put_contents($tempPath, $fileContent);
                $mediaPath = $tempPath;
                $isTempFile = true;
            } else {
                if (! file_exists($mediaPath)) {
                    $storagePath = storage_path('app/public/'.$mediaPath);
                    if (file_exists($storagePath)) {
                        $mediaPath = $storagePath;
                    }
                }

                $isTempFile = false;
            }

            if (! file_exists($mediaPath)) {
                throw new Exception("Media file not found: {$mediaPath}");
            }

            $upload = $this->uploadMediaWithOAuth2($mediaPath, $accessToken);

            if (isset($isTempFile) && $isTempFile && file_exists($mediaPath)) {
                @unlink($mediaPath);
            }

            return $upload;
        } catch (\Throwable $e) {
            $friendlyMessage = XApiErrorParser::getFriendlyMessage($e);

            Log::error('X API: Failed to upload media', [
                'error' => $e->getMessage(),
                'friendly_message' => $friendlyMessage,
                'media_path' => $mediaPath,
            ]);

            $exception = $e instanceof Exception ? $e : new Exception($e->getMessage(), $e->getCode(), $e);
            throw new Exception($friendlyMessage, $exception->getCode(), $exception);
        }
    }

    /**
     * @throws Exception
     */
    public function searchTweets(string $query, array $options = []): array
    {
        try {
            $params = array_merge([
                'max_results' => $options['max_results'] ?? 10,
                'tweet.fields' => 'created_at,author_id,public_metrics,text',
                'expansions' => 'author_id',
                'user.fields' => 'username,name',
            ], $options);

            return Twitter::forApiV2()->searchRecent($query, $params);
        } catch (Exception $e) {
            Log::error('X API: Failed to search tweets', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getTweetMetrics(string $tweetId): array
    {
        try {
            $params = [
                'ids' => $tweetId,
                'tweet.fields' => 'public_metrics,created_at',
            ];

            $response = Twitter::forApiV2()->getTweet($tweetId, $params);

            if (! isset($response['data'])) {
                throw new Exception('Failed to get tweet metrics from X API response');
            }

            return $response['data'];
        } catch (Exception $e) {
            Log::error('X API: Failed to get tweet metrics', [
                'error' => $e->getMessage(),
                'tweet_id' => $tweetId,
            ]);

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getMentions(array $options = []): array
    {
        $dto = $this->getMentionsAsDto($options);

        return $dto->toArray();
    }

    /**
     * @throws Exception
     */
    public function getMentionsAsDto(array $options = []): XMentionsResponse
    {
        try {
            $userId = config('x-suite.twitter.user_id');

            if (! $userId) {
                throw new Exception('x-suite.twitter.user_id config is required for mentions. Set TWITTER_USER_ID in your .env file.');
            }

            $params = array_merge([
                'max_results' => $options['max_results'] ?? 10,
                'tweet.fields' => 'created_at,author_id,public_metrics,text,in_reply_to_user_id',
                'expansions' => 'author_id',
                'user.fields' => 'username,name',
            ], $options);

            $response = Twitter::forApiV2()->userMentions($userId, $params);

            return XMentionsResponse::fromApiResponse($response);
        } catch (Exception $e) {
            Log::error('X API: Failed to get mentions', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function getUserByUsername(string $username): array
    {
        $dto = $this->getUserByUsernameAsDto($username);

        return $dto->toArray();
    }

    /**
     * @throws Exception
     */
    public function getUserByUsernameAsDto(string $username): XUserData
    {
        try {
            $params = [
                'user.fields' => 'id,username,name,public_metrics,created_at',
            ];

            $response = Twitter::forApiV2()->getUserByUsername($username, $params);

            if (! isset($response['data'])) {
                throw new Exception('Failed to get user from X API response');
            }

            return XUserData::fromApiResponse($response['data']);
        } catch (Exception $e) {
            Log::error('X API: Failed to get user by username', [
                'error' => $e->getMessage(),
                'username' => $username,
            ]);

            throw $e;
        }
    }

    public function getRateLimitStatus(): array
    {
        return [];
    }

    /**
     * @throws Exception
     */
    public function searchTweetsAsDto(string $query, array $options = []): XSearchResponse
    {
        $response = $this->searchTweets($query, $options);

        return XSearchResponse::fromApiResponse($response);
    }

    /**
     * @throws Exception
     */
    public function getTweetMetricsAsDto(string $tweetId): XTweetData
    {
        $data = $this->getTweetMetrics($tweetId);

        return XTweetData::fromApiResponse($data);
    }

    /**
     * @throws Exception
     */
    protected function postTweetWithOAuth2(
        string $text,
        ?array $mediaIds,
        ?string $replyToTweetId,
        string $accessToken
    ): array {
        $client = new GuzzleClient([
            'base_uri' => 'https://api.twitter.com/2/',
        ]);

        $payload = ['text' => $text];

        if (! empty($mediaIds)) {
            $mediaIds = array_filter($mediaIds, fn ($id) => $id !== null);
            if (! empty($mediaIds)) {
                $payload['media'] = ['media_ids' => array_values($mediaIds)];
            }
        }

        if ($replyToTweetId !== null && $replyToTweetId !== '') {
            $payload['reply'] = ['in_reply_to_tweet_id' => $replyToTweetId];
        }

        $response = $client->post('tweets', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ],
            RequestOptions::JSON => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! isset($data['data']['id'])) {
            throw new Exception('Failed to get tweet ID from X API response: '.json_encode($data));
        }

        return $data['data'];
    }

    /**
     * @throws Exception
     */
    protected function uploadMediaWithOAuth2(string $mediaPath, string $accessToken): string
    {
        $client = new GuzzleClient([
            'base_uri' => 'https://upload.twitter.com/1.1/',
        ]);

        $fileContents = file_get_contents($mediaPath);
        $mimeType = mime_content_type($mediaPath) ?: 'image/jpeg';

        $response = $client->post('media/upload.json', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
            RequestOptions::MULTIPART => [
                [
                    'name' => 'media',
                    'contents' => $fileContents,
                    'filename' => basename($mediaPath),
                    'headers' => [
                        'Content-Type' => $mimeType,
                    ],
                ],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $mediaIdString = $data['media_id_string'] ?? $data['media_id'] ?? null;

        if (! $mediaIdString) {
            throw new Exception('Failed to get media ID from X API response: '.json_encode($data));
        }

        return (string) $mediaIdString;
    }
}
