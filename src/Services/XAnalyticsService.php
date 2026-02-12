<?php

namespace JonesRussell\XSuite\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Models\XAnalytics;
use JonesRussell\XSuite\Models\XPost;

class XAnalyticsService
{
    public function __construct(
        protected XApiService $xApiService
    ) {}

    /**
     * @throws Exception
     */
    public function fetchMetrics(string $tweetId, ?XPost $xPost = null): XAnalytics
    {
        try {
            $tweetData = $this->xApiService->getTweetMetrics($tweetId);
            $metrics = $tweetData['public_metrics'] ?? [];

            $analyticsData = [
                'tweet_id' => $tweetId,
                'x_post_id' => $xPost?->id,
                'impressions' => $metrics['impression_count'] ?? 0,
                'likes' => $metrics['like_count'] ?? 0,
                'retweets' => $metrics['retweet_count'] ?? 0,
                'replies' => $metrics['reply_count'] ?? 0,
                'bookmarks' => $metrics['bookmark_count'] ?? 0,
                'quotes' => $metrics['quote_count'] ?? 0,
                'profile_clicks' => $metrics['profile_clicks'] ?? 0,
                'link_clicks' => $metrics['link_clicks'] ?? 0,
                'recorded_at' => now(),
            ];

            return XAnalytics::create($analyticsData);
        } catch (Exception $e) {
            Log::error('X Analytics: Failed to fetch metrics', [
                'tweet_id' => $tweetId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function syncPublishedPosts(int $limit = 50): int
    {
        $posts = XPost::published()
            ->whereNotNull('x_post_id')
            ->limit($limit)
            ->get();

        $synced = 0;

        foreach ($posts as $post) {
            try {
                $this->fetchMetrics($post->x_post_id, $post);
                $synced++;

                usleep(250000);
            } catch (Exception $e) {
                Log::warning('X Analytics: Failed to sync post', [
                    'x_post_id' => $post->id,
                    'tweet_id' => $post->x_post_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    public function getPerformanceReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = XAnalytics::query();

        if ($startDate) {
            $query->where('recorded_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate);
        }

        $latestAnalytics = $query->latest('recorded_at')
            ->get()
            ->unique('tweet_id');

        return [
            'total_impressions' => $latestAnalytics->sum('impressions'),
            'total_likes' => $latestAnalytics->sum('likes'),
            'total_retweets' => $latestAnalytics->sum('retweets'),
            'total_replies' => $latestAnalytics->sum('replies'),
            'total_bookmarks' => $latestAnalytics->sum('bookmarks'),
            'total_quotes' => $latestAnalytics->sum('quotes'),
            'avg_engagement_rate' => $this->calculateEngagementRate($latestAnalytics),
            'total_posts' => $latestAnalytics->count(),
        ];
    }

    public function getTopPerformers(int $limit = 10, string $metric = 'impressions'): Collection
    {
        $latestAnalytics = XAnalytics::query()
            ->latest('recorded_at')
            ->get()
            ->unique('tweet_id')
            ->sortByDesc($metric)
            ->take($limit);

        return $latestAnalytics->load('xPost');
    }

    protected function calculateEngagementRate(Collection $analytics): float
    {
        $totalImpressions = $analytics->sum('impressions');
        $totalEngagement = $analytics->sum('likes') +
            $analytics->sum('retweets') +
            $analytics->sum('replies');

        if ($totalImpressions === 0) {
            return 0.0;
        }

        return round(($totalEngagement / $totalImpressions) * 100, 2);
    }
}
