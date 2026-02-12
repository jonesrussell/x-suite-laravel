<?php

namespace JonesRussell\XSuite\Console\Commands;

use Illuminate\Console\Command;
use JonesRussell\XSuite\Services\XAutoReplyService;

class ProcessXAutoReplies extends Command
{
    protected $signature = 'x-suite:process-auto-replies {--limit=10 : Maximum mentions to process}';

    protected $description = 'Process mentions and send auto-replies based on rules';

    public function handle(XAutoReplyService $autoReplyService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing up to {$limit} mentions...");

        $repliesSent = $autoReplyService->processMentions($limit);

        $this->info("Sent {$repliesSent} auto-replies.");

        return Command::SUCCESS;
    }
}
