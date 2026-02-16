<?php

namespace JonesRussell\XSuite\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JonesRussell\XSuite\Services\XAutoReplyService;

class ProcessXAutoReplies extends Command
{
    protected $signature = 'x-suite:process-auto-replies {--limit=10 : Maximum mentions to process}';

    protected $description = 'Process mentions and send auto-replies based on rules';

    public function handle(XAutoReplyService $autoReplyService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing up to {$limit} mentions...");

        try {
            $repliesSent = $autoReplyService->processMentions($limit);

            $this->info("Sent {$repliesSent} auto-replies.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('x-suite:process-auto-replies failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
