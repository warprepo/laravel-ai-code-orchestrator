<?php

namespace Warp\LaravelAiCodeOrchestrator\Support;

use Throwable;

class CodeContextBuilder
{
    public function build(Throwable $throwable, int $snippetLines, int $depth, int $maxChars, int $maxFrames, int $maxBlockLines, bool $stripComments, array $excludeGlobs): array
    {
        $frames = $this->collectFrames($throwable, $excludeGlobs);
        $frames = array_slice($frames, 0, max(1, $depth + 1));
        $frames = array_slice($frames, 0, max(1, $maxFrames));

        $snippets = [];
        $traceLines = [];
        $offendingLine = null;

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;
            $function = $this->formatFunction($frame);

            if (! $file || ! $line) {
                continue;
            }

            $traceLines[] = trim(($function ? $function.' ' : '').$this->relativePath($file).':'.$line);
            if ($offendingLine === null) {
                $offendingLine = $this->getLineText($file, (int) $line);
            }
            $snippets[] = $this->buildSnippet($file, (int) $line, $snippetLines, $maxBlockLines, $stripComments);
        }

        $contextText = implode("\n\n", array_filter($snippets));
        $contextText = $this->truncate($contextText, $maxChars);

        return [
            'code_context' => $contextText,
            'filtered_trace' => implode("\n", $traceLines),
            'offending_line' => $offendingLine,
        ];
    }

    private function collectFrames(Throwable $throwable, array $excludeGlobs): array
    {
        $basePath = base_path();
        $primary = [
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'function' => null,
            'class' => null,
            'type' => null,
        ];

        $frames = array_merge([$primary], $throwable->getTrace());
        $filtered = [];

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (! $file) {
                continue;
            }

            if (! $this->isAppFile($file, $basePath, $excludeGlobs)) {
                continue;
            }

            $filtered[] = $frame;
        }

        return $filtered;
    }

    private function isAppFile(string $file, string $basePath, array $excludeGlobs): bool
    {
        $normalized = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', $basePath), '/');

        if (! str_starts_with($normalized, $base.'/app/') &&
            ! str_starts_with($normalized, $base.'/packages/') &&
            ! str_starts_with($normalized, $base.'/resources/')) {
            return false;
        }

        if (str_contains($normalized, '/vendor/') || str_contains($normalized, '/node_modules/')) {
            return false;
        }

        $relative = $this->relativePath($file);
        $relative = str_replace('\\', '/', $relative);

        foreach ($excludeGlobs as $pattern) {
            if ($this->matchGlob($relative, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function buildSnippet(string $file, int $line, int $snippetLines, int $maxBlockLines, bool $stripComments): string
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines) || $line < 1) {
            return '';
        }

        $half = (int) floor($snippetLines / 2);
        $start = max(1, $line - $half);
        $end = min(count($lines), $start + $snippetLines - 1);

        $snippet = [];

        $blockLines = array_slice($lines, $start - 1, $end - $start + 1);
        if ($stripComments) {
            $blockLines = $this->stripComments($blockLines);
        }

        for ($i = 0; $i < count($blockLines); $i++) {
            $lineNumber = $start + $i;
            $snippet[] = str_pad((string) $lineNumber, 5, ' ', STR_PAD_LEFT).' | '.$blockLines[$i];
        }

        $snippet = $this->summarizeBlock($snippet, $maxBlockLines);

        return "File: ".$this->relativePath($file).":{$line}\n".
            "```\n".implode("\n", $snippet)."\n```";
    }

    private function getLineText(string $file, int $line): ?string
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines) || $line < 1 || $line > count($lines)) {
            return null;
        }

        return rtrim($lines[$line - 1]);
    }

    private function relativePath(string $file): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/');
        $normalized = str_replace('\\', '/', $file);

        if (str_starts_with($normalized, $base.'/')) {
            $relative = substr($normalized, strlen($base) + 1);
        } else {
            $relative = $file;
        }

        return str_replace('packages/warp/', 'packages/', $relative);
    }

    private function formatFunction(array $frame): ?string
    {
        $class = $frame['class'] ?? null;
        $type = $frame['type'] ?? null;
        $function = $frame['function'] ?? null;

        if (! $function) {
            return null;
        }

        return $class ? $class.$type.$function.'()' : $function.'()';
    }

    private function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n... [truncated]";
    }

    private function summarizeBlock(array $lines, int $maxBlockLines): array
    {
        if ($maxBlockLines <= 0 || count($lines) <= $maxBlockLines) {
            return $lines;
        }

        $headCount = (int) max(5, floor($maxBlockLines * 0.6));
        $tailCount = max(3, $maxBlockLines - $headCount - 1);
        $omitted = count($lines) - ($headCount + $tailCount);

        return array_merge(
            array_slice($lines, 0, $headCount),
            ['   ... [omessi '.$omitted.' linee] ...'],
            array_slice($lines, -$tailCount)
        );
    }

    private function matchGlob(string $path, string $pattern): bool
    {
        $pattern = str_replace('\\', '/', $pattern);
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '.'], $pattern);

        return (bool) preg_match('/^'.$pattern.'$/', $path);
    }

    private function stripComments(array $lines): array
    {
        $result = [];
        $inBlock = false;
        $inBlade = false;

        foreach ($lines as $line) {
            $current = $line;

            if ($inBlade) {
                $endPos = strpos($current, '--}}');
                if ($endPos === false) {
                    $result[] = '';
                    continue;
                }
                $current = substr($current, $endPos + 4);
                $inBlade = false;
            }

            if ($inBlock) {
                $endPos = strpos($current, '*/');
                if ($endPos === false) {
                    $result[] = '';
                    continue;
                }
                $current = substr($current, $endPos + 2);
                $inBlock = false;
            }

            while (true) {
                $bladeStart = strpos($current, '{{--');
                $blockStart = strpos($current, '/*');

                $startPositions = array_filter([$bladeStart, $blockStart], static fn ($pos) => $pos !== false);
                if (empty($startPositions)) {
                    break;
                }

                $start = min($startPositions);

                if ($bladeStart !== false && $start === $bladeStart) {
                    $before = substr($current, 0, $start);
                    $endPos = strpos($current, '--}}', $start + 4);
                    if ($endPos === false) {
                        $current = $before;
                        $inBlade = true;
                        break;
                    }
                    $current = $before.substr($current, $endPos + 4);
                    continue;
                }

                $before = substr($current, 0, $start);
                $endPos = strpos($current, '*/', $start + 2);
                if ($endPos === false) {
                    $current = $before;
                    $inBlock = true;
                    break;
                }
                $current = $before.substr($current, $endPos + 2);
            }

            if (! $inBlock && ! $inBlade) {
                $current = preg_replace('/\\s*\\/\\/.*$/', '', $current);
                $current = preg_replace('/\\s*#.*$/', '', $current);
            }

            $result[] = rtrim($current);
        }

        return $result;
    }
}
