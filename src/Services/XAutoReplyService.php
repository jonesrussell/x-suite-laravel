<?php

namespace JonesRussell\XSuite\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Data\X\XTweetData;
use JonesRussell\XSuite\Models\XAutoReplyRule;

class XAutoReplyService
{
    public function __construct(
        protected XApiService $xApiService
    ) {}

    public function processMentions(int $limit = 10): int
    {
        try {
            $mentionsResponse = $this->xApiService->getMentionsAsDto(['max_results' => $limit]);

            if ($mentionsResponse->isEmpty()) {
                return 0;
            }

            $rules = XAutoReplyRule::active()->orderedByPriority()->get();

            if ($rules->isEmpty()) {
                return 0;
            }

            $repliesSent = 0;

            foreach ($mentionsResponse->tweets as $tweet) {
                $text = strtolower($tweet->text);

                foreach ($rules as $rule) {
                    if ($rule->matches($text)) {
                        try {
                            $this->checkAndReply($tweet, $rule);
                            $repliesSent++;
                            break;
                        } catch (Exception $e) {
                            Log::warning('X Auto Reply: Failed to send reply', [
                                'tweet_id' => $tweet->id,
                                'rule_id' => $rule->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                usleep(1000000);
            }

            return $repliesSent;
        } catch (Exception $e) {
            Log::error('X Auto Reply: Failed to process mentions', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function checkAndReply(XTweetData $tweet, XAutoReplyRule $rule): bool
    {
        $context = [
            'author' => $tweet->authorId,
            'tweet_id' => $tweet->id,
        ];

        $replyContent = $rule->generateReply($context);

        return $this->sendReply($tweet->id, $replyContent);
    }

    /**
     * @throws Exception
     */
    public function sendReply(string $tweetId, string $content): bool
    {
        try {
            $this->xApiService->postTweet($content, null, $tweetId);

            Log::info('X Auto Reply: Reply sent', [
                'reply_to_tweet_id' => $tweetId,
                'content_length' => strlen($content),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('X Auto Reply: Failed to send reply', [
                'reply_to_tweet_id' => $tweetId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function testRule(XAutoReplyRule $rule, string $text): bool
    {
        return $rule->matches($text);
    }
}
