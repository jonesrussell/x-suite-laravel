<?php

namespace JonesRussell\XSuite\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Models\XTrendKeyword;
use JonesRussell\XSuite\Models\XTrendResult;

class XTrendMonitoringService
{
    public function __construct(
        protected XApiService $xApiService
    ) {}

    /**
     * @throws Exception
     */
    public function searchKeyword(XTrendKeyword $keyword, int $maxResults = 10): int
    {
        try {
            $query = $this->buildSearchQuery($keyword);

            $response = $this->xApiService->searchTweetsAsDto($query, [
                'max_results' => min($maxResults, 100),
            ]);

            $resultsCount = 0;

            foreach ($response->tweets as $tweet) {
                $author = $tweet->author;
                $metrics = $tweet->metrics;

                $existing = XTrendResult::where('tweet_id', $tweet->id)->first();

                if (! $existing) {
                    XTrendResult::create([
                        'trend_keyword_id' => $keyword->id,
                        'tweet_id' => $tweet->id,
                        'author_username' => $author?->username ?? 'unknown',
                        'content' => $tweet->text,
                        'like_count' => $metrics?->likeCount ?? 0,
                        'retweet_count' => $metrics?->retweetCount ?? 0,
                        'reply_count' => $metrics?->replyCount ?? 0,
                        'tweet_created_at' => $tweet->createdAt ?? now(),
                        'url' => $tweet->getTweetUrl(),
                    ]);

                    $resultsCount++;
                }
            }

            $keyword->markSearched($resultsCount);

            return $resultsCount;
        } catch (Exception $e) {
            Log::error('X Trend Monitoring: Failed to search keyword', [
                'keyword_id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function monitorAllActiveKeywords(): int
    {
        $keywords = XTrendKeyword::active()->get();
        $totalResults = 0;

        foreach ($keywords as $keyword) {
            try {
                $results = $this->searchKeyword($keyword, 10);
                $totalResults += $results;

                usleep(500000);
            } catch (Exception $e) {
                Log::warning('X Trend Monitoring: Failed to monitor keyword', [
                    'keyword_id' => $keyword->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalResults;
    }

    public function getRecentTrends(int $limit = 20): Collection
    {
        return XTrendResult::query()
            ->with('keyword')
            ->recent(7)
            ->orderBy('like_count', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function buildSearchQuery(XTrendKeyword $keyword): string
    {
        $keywordValue = $keyword->keyword;

        return match ($keyword->type) {
            XTrendKeyword::TYPE_HASHTAG => $keywordValue[0] === '#' ? $keywordValue : "#{$keywordValue}",
            XTrendKeyword::TYPE_PHRASE => '"'.$keywordValue.'"',
            default => $keywordValue,
        };
    }
}
