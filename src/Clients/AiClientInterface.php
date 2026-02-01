<?php

namespace Warp\LaravelAiCodeOrchestrator\Clients;

use Throwable;

interface AiClientInterface
{
    public function analyze(Throwable $throwable, array $context): string;
}
