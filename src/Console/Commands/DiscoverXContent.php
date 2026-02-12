<?php

namespace JonesRussell\XSuite\Console\Commands;

use Illuminate\Console\Command;
use JonesRussell\XSuite\Services\XContentDiscoveryService;

class DiscoverXContent extends Command
{
    protected $signature = 'x-suite:discover-content {--min-likes=10 : Minimum likes to include} {--max-results=50 : Maximum results to discover}';

    protected $description = 'Discover and curate high-quality content from X';

    public function handle(XContentDiscoveryService $discoveryService): int
    {
        $minLikes = (int) $this->option('min-likes');
        $maxResults = (int) $this->option('max-results');

        $this->info("Discovering content with minimum {$minLikes} likes...");

        $filters = [
            'min_likes' => $minLikes,
            'max_results' => $maxResults,
        ];

        try {
            $discovered = $discoveryService->discoverContent($filters);

            $this->info("Discovered {$discovered} new curated posts.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to discover content: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
