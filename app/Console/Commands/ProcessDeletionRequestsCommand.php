<?php

namespace App\Console\Commands;

use App\Domain\Profile\Services\AccountDeletionService;
use App\Models\DeletionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDeletionRequestsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:process-deletion-requests';

    /**
     * @var string
     */
    protected $description = 'Process due account deletion requests: purge related user data, revoke tokens, and mark requests as completed';

    public function __construct(private readonly AccountDeletionService $accountDeletionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $processed = 0;
        $failed = 0;

        DeletionRequest::query()
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($deletionRequests) use (&$processed, &$failed) {
                foreach ($deletionRequests as $deletionRequest) {
                    try {
                        $this->accountDeletionService->process($deletionRequest);
                        $processed++;
                    } catch (Throwable $e) {
                        $failed++;

                        Log::error('Failed to process deletion request.', [
                            'deletion_request_id' => $deletionRequest->id,
                            'exception' => $e,
                        ]);
                    }
                }
            });

        $this->info("Processed {$processed} deletion request(s).");

        if ($failed > 0) {
            $this->error("Failed to process {$failed} deletion request(s). See logs for details.");
        }

        return self::SUCCESS;
    }
}
