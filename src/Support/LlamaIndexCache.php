<?php

namespace Warp\LaravelAiCodeOrchestrator\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class LlamaIndexCache
{
    public function getIndexData(array $config): array
    {
        $enabled = (bool) ($config['enabled'] ?? true);
        if (! $enabled) {
            return ['index' => '', 'count' => 0];
        }

        $cacheSeconds = (int) ($config['cache_seconds'] ?? 3600);
        $cacheKey = (string) ($config['cache_key'] ?? 'ai-code-orchestrator.llama.file_index');

        try {
            $value = Cache::remember($cacheKey, $cacheSeconds, function () use ($config): array {
                $builder = new LlamaFileIndexBuilder();
                $roots = (array) ($config['roots'] ?? ['app', 'config', 'packages']);
                $excludeGlobs = (array) ($config['exclude_globs'] ?? []);
                $extensions = (array) ($config['extensions'] ?? []);
                $maxFiles = (int) ($config['max_files'] ?? 2000);
                $maxChars = (int) ($config['max_chars'] ?? 6000);

                return $builder->buildWithCount($roots, $excludeGlobs, $extensions, $maxFiles, $maxChars);
            });
        } catch (Throwable) {
            return ['index' => '', 'count' => 0];
        }

        if (is_array($value)) {
            return [
                'index' => (string) ($value['index'] ?? ''),
                'count' => (int) ($value['count'] ?? 0),
            ];
        }

        if (is_string($value)) {
            $count = $value === '' ? 0 : (substr_count($value, "\n") + 1);
            return ['index' => $value, 'count' => $count];
        }

        return ['index' => '', 'count' => 0];
    }
}
