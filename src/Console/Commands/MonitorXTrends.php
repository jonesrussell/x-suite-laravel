<?php

namespace JonesRussell\XSuite\Console\Commands;

use Illuminate\Console\Command;
use JonesRussell\XSuite\Services\XTrendMonitoringService;

class MonitorXTrends extends Command
{
    protected $signature = 'x-suite:monitor-trends';

    protected $description = 'Search all active trend keywords and store results';

    public function handle(XTrendMonitoringService $trendService): int
    {
        $this->info('Monitoring active trend keywords...');

        $results = $trendService->monitorAllActiveKeywords();

        $this->info("Found {$results} new trending tweets.");

        return Command::SUCCESS;
    }
}
