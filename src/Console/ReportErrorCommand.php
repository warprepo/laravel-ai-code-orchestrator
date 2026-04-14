<?php

namespace Warp\LaravelAiCodeOrchestrator\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;
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
        {--random : Genera uno scenario errore casuale per test}
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

        $throwable = $this->buildThrowable($message, (bool) $this->option('random'));

        $service->handleThrowable($throwable, $context);

        $this->info('Report inviato.');

        return self::SUCCESS;
    }

    private function buildThrowable(string $message, bool $random): Throwable
    {
        if (! $random) {
            return new RuntimeException($message);
        }

        $scenario = random_int(1, 6);
        $seed = substr(hash('sha256', microtime(true).$message.random_int(1000, 9999)), 0, 8);

        return match ($scenario) {
            1 => new RuntimeException("[RUNTIME][$seed] $message"),
            2 => new \InvalidArgumentException("[INVALID_ARGUMENT][$seed] Payload non valido: $message"),
            3 => new \LogicException("[LOGIC][$seed] Stato applicativo incoerente: $message"),
            4 => new \DomainException("[DOMAIN][$seed] Regola di dominio violata: $message"),
            5 => new \OutOfBoundsException("[OUT_OF_BOUNDS][$seed] Indice fuori limite: $message"),
            default => new \UnexpectedValueException("[UNEXPECTED_VALUE][$seed] Valore inatteso: $message"),
        };
    }
}
