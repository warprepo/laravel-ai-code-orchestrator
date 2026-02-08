<?php

namespace Warp\LaravelAiCodeOrchestrator\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Warp\LaravelAiCodeOrchestrator\Services\ErrorService;

class ReportErrorCommand extends Command
{
    /** @example php artisan ai-orchestrator:report "Errore test Gemini" --provider=gemini --language=it */

    protected $signature = 'ai-orchestrator:report
        {message : Messaggio di errore}
        {--url= : URL della richiesta}
        {--method= : Metodo HTTP}
        {--user_id= : ID utente}
        {--provider= : Provider AI (openai|groq|gemini|llama)}
        {--language= : Lingua risposta AI (it|en)}
    ';

    protected $description = 'Invia un report di errore al sistema AI orchestrator.';

    public function handle(ErrorService $service): int
    {
        $message = (string) $this->argument('message');

        if ($this->option('provider')) {
            config()->set('ai-code-orchestrator.ai.provider', $this->option('provider'));
        }

        if ($this->option('language')) {
            config()->set('ai-code-orchestrator.ai.language', $this->option('language'));
        }

        $context = [
            'url' => $this->option('url'),
            'method' => $this->option('method'),
            'user_id' => $this->option('user_id'),
        ];

        $service->handleThrowable(new RuntimeException($message), $context);

        $this->info('Report inviato.');

        return self::SUCCESS;
    }
}
