<?php

namespace JonesRussell\XSuite\Console\Commands;

use Illuminate\Console\Command;
use JonesRussell\XSuite\Services\XAnalyticsService;

class SyncXAnalytics extends Command
{
    protected $signature = 'x-suite:sync-analytics {--limit=50 : Maximum number of posts to sync}';

    protected $description = 'Sync analytics metrics for all published X posts';

    public function handle(XAnalyticsService $analyticsService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Syncing analytics for up to {$limit} published posts...");

        $synced = $analyticsService->syncPublishedPosts($limit);

        $this->info("Successfully synced metrics for {$synced} posts.");

        return Command::SUCCESS;
    }
}
