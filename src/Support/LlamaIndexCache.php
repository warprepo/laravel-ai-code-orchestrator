<?php

namespace Warp\LaravelAiCodeOrchestrator\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class LlamaIndexCache
{
    private const int DEFAULT_INDEX_MAX_FILES = 10;
    private const int DEFAULT_INDEX_MAX_CHARS = 1000;

    public function getIndexData(array $config): array
    {
        $enabled = (bool) ($config['enabled'] ?? true);
        if (! $enabled) {
            return ['index' => '', 'count' => 0];
        }

        $cacheSeconds = (int) ($config['cache_seconds'] ?? 3600);
        $cacheKey = (string) ($config['cache_key'] ?? 'ai-code-orchestrator.llama.file_index');
        $configuredMaxFiles = isset($config['max_files']) ? (int) $config['max_files'] : self::DEFAULT_INDEX_MAX_FILES;
        $configuredMaxChars = isset($config['max_chars']) ? (int) $config['max_chars'] : self::DEFAULT_INDEX_MAX_CHARS;
        $maxFiles = max(1, min(self::DEFAULT_INDEX_MAX_FILES, $configuredMaxFiles));
        $maxChars = max(200, min(self::DEFAULT_INDEX_MAX_CHARS, $configuredMaxChars));
        $cacheKey .= '.max_files_'.$maxFiles.'_max_chars_'.$maxChars;

        try {
            $value = Cache::remember($cacheKey, $cacheSeconds, function () use ($config, $maxFiles, $maxChars): array {
                $builder = new LlamaFileIndexBuilder();
                $roots = (array) ($config['roots'] ?? ['app', 'config', 'packages']);
                $excludeGlobs = (array) ($config['exclude_globs'] ?? []);
                $extensions = (array) ($config['extensions'] ?? []);

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
