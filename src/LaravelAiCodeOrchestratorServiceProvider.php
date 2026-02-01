<?php

namespace Warp\LaravelAiCodeOrchestrator;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Warp\LaravelAiCodeOrchestrator\Clients\AiClientInterface;
use Warp\LaravelAiCodeOrchestrator\Clients\GeminiClient;
use Warp\LaravelAiCodeOrchestrator\Clients\GroqClient;
use Warp\LaravelAiCodeOrchestrator\Clients\NullAiClient;
use Warp\LaravelAiCodeOrchestrator\Clients\OpenAiClient;
use Warp\LaravelAiCodeOrchestrator\Console\ReportErrorCommand;
use Warp\LaravelAiCodeOrchestrator\Services\ErrorService;

class LaravelAiCodeOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-code-orchestrator.php', 'ai-code-orchestrator');

        $this->app->singleton(AiClientInterface::class, function () {
            $provider = config('ai-code-orchestrator.ai.provider', 'openai');

            return match ($provider) {
                'openai' => new OpenAiClient(),
                'groq' => new GroqClient(),
                'gemini' => new GeminiClient(),
                default => new NullAiClient(),
            };
        });

        $this->app->singleton(ErrorService::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'warp');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/ai-code-orchestrator.php' => config_path('ai-code-orchestrator.php'),
        ], 'ai-code-orchestrator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReportErrorCommand::class,
            ]);
        }

        $this->registerExceptionHook();
    }

    /**
     * @throws BindingResolutionException
     */
    private function registerExceptionHook(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $handler->reportable(function (Throwable $throwable) {
            if (! $this->shouldReport($throwable)) {
                return;
            }

            try {
                $context = $this->buildContext();
                $this->app->make(ErrorService::class)->handleThrowable($throwable, $context);
            } catch (Throwable) {
                // Evita di interrompere il report standard di Laravel.
            }
        });
    }

    private function shouldReport(Throwable $throwable): bool
    {
        $ignored = config('ai-code-orchestrator.ignore_exceptions', []);

        foreach ($ignored as $class) {
            if ($throwable instanceof $class) {
                return false;
            }
        }

        return true;
    }

    private function buildContext(): array
    {
        $request = request();

        return [
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'user_id' => $request?->user()?->getAuthIdentifier(),
        ];
    }
}
