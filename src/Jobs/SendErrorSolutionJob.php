<?php

namespace Warp\LaravelAiCodeOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Warp\LaravelAiCodeOrchestrator\Mail\ErrorSolutionMail;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;

class SendErrorSolutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $errorReportId)
    {
    }

    public function handle(): void
    {
        if ($this->shouldLogDebug()) {
            Log::info('ai_orchestrator.mail.job_start', [
                'report_id' => $this->errorReportId,
                'queue' => $this->queue,
                'attempts' => $this->attempts(),
            ]);
        }

        $report = ErrorReport::find($this->errorReportId);

        if (! $report) {
            if ($this->shouldLogDebug()) {
                Log::warning('ai_orchestrator.mail.job_skip', [
                    'report_id' => $this->errorReportId,
                    'reason' => 'report_not_found',
                ]);
            }

            return;
        }

        $to = (string) config('ai-code-orchestrator.admin_email');
        $startedAt = microtime(true);

        Mail::to($to)->send(new ErrorSolutionMail($report));

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $report->status = 'emailed';
        $report->save();

        if ($this->shouldLogDebug()) {
            Log::info('ai_orchestrator.mail.sent', [
                'report_id' => $report->id,
                'to' => $to,
                'status' => $report->status,
                'duration_ms' => $durationMs,
            ]);
        }
    }

    private function shouldLogDebug(): bool
    {
        return (bool) config('app.debug', false);
    }
}
