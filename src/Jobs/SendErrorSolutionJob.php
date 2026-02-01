<?php

namespace Warp\LaravelAiCodeOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        $report = ErrorReport::find($this->errorReportId);

        if (! $report) {
            return;
        }

        Mail::to(config('ai-code-orchestrator.admin_email'))
            ->send(new ErrorSolutionMail($report));

        $report->status = 'emailed';
        $report->save();
    }
}
