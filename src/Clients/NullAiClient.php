<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Throwable;

class NullAiClient implements AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string
    {
        return 'Provider AI non configurato.';
    }
}
