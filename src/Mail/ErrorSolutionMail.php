<?php

namespace Warp\LaravelAiCodeOrchestrator\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;

class ErrorSolutionMail extends Mailable
{
    use Queueable, SerializesModels;

    public ErrorReport $report;

    public function __construct(ErrorReport $report)
    {
        $this->report = $report;
    }

    public function build()
    {
        return $this->subject(config('ai-code-orchestrator.mail_subject'))
            ->view('warp::emails.error_solution')
            ->with(['report' => $this->report]);
    }
}
