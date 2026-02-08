<?php

namespace Warp\LaravelAiCodeOrchestrator\Support;

class LlamaFileIndexBuilder
{
    public function build(array $roots, array $excludeGlobs, array $extensions, int $maxFiles, int $maxChars): string
    {
        $data = $this->buildWithCount($roots, $excludeGlobs, $extensions, $maxFiles, $maxChars);
        return $data['index'];
    }

    public function buildWithCount(array $roots, array $excludeGlobs, array $extensions, int $maxFiles, int $maxChars): array
    {
        $basePath = rtrim(str_replace('\\', '/', base_path()), '/');
        $paths = [];

        foreach ($roots as $root) {
            $rootPath = $basePath.'/'.trim((string) $root, '/');
            if (! is_dir($rootPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $fullPath = str_replace('\\', '/', $file->getPathname());
                $relative = $this->relativePath($fullPath, $basePath);

                if (! $this->matchesExtensions($relative, $extensions)) {
                    continue;
                }

                if ($this->matchesExclude($relative, $excludeGlobs)) {
                    continue;
                }

                $paths[] = $relative;
                if ($maxFiles > 0 && count($paths) >= $maxFiles) {
                    break 2;
                }
            }
        }

        $index = implode("\n", $paths);
        $index = $this->truncate($index, $maxChars);

        return [
            'index' => $index,
            'count' => count($paths),
        ];
    }

    private function matchesExtensions(string $relative, array $extensions): bool
    {
        if (count($extensions) === 0) {
            return true;
        }

        $relative = strtolower($relative);

        foreach ($extensions as $ext) {
            $ext = strtolower((string) $ext);
            if ($ext === '') {
                continue;
            }

            $suffix = $ext[0] === '.' ? $ext : '.'.$ext;
            if (str_ends_with($relative, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function matchesExclude(string $relative, array $excludeGlobs): bool
    {
        $normalized = str_replace('\\', '/', $relative);

        foreach ($excludeGlobs as $pattern) {
            if ($this->matchGlob($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $file, string $basePath): string
    {
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $normalized = str_replace('\\', '/', $file);

        if (str_starts_with($normalized, $basePath.'/')) {
            return substr($normalized, strlen($basePath) + 1);
        }

        return $file;
    }

    private function matchGlob(string $path, string $pattern): bool
    {
        $pattern = str_replace('\\', '/', (string) $pattern);
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*\*', '.*', $pattern);
        $pattern = str_replace('\*', '[^/]*', $pattern);

        return (bool) preg_match('/^'.$pattern.'$/', $path);
    }

    private function truncate(string $text, int $maxChars): string
    {
        if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n... [truncated]";
    }
}
