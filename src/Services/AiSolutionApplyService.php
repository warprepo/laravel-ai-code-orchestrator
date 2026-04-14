<?php

namespace Warp\LaravelAiCodeOrchestrator\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Warp\LaravelAiCodeOrchestrator\Models\ErrorReport;

class AiSolutionApplyService
{
    public function apply(ErrorReport $report): array
    {
        $solution = (string) ($report->ai_solution ?? '');
        $patch = $this->extractPatch($solution);

        if ($patch === null) {
            return [
                'ok' => false,
                'message' => 'Nessuna patch trovata nella risposta AI. Serve un blocco ```patch``` o ```diff```.',
            ];
        }

        $patchDir = storage_path('app/ai-code-orchestrator/patches');
        File::ensureDirectoryExists($patchDir);

        $patchFile = $patchDir.'/report-'.$report->id.'-'.date('Ymd_His').'.patch';
        File::put($patchFile, $patch);

        $process = new Process([
            'git',
            '-C',
            base_path(),
            'apply',
            '--reject',
            '--whitespace=nowarn',
            $patchFile,
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'ok' => false,
                'message' => 'Patch non applicata: '.$process->getErrorOutput(),
                'patch_file' => $patchFile,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Patch applicata correttamente.',
            'patch_file' => $patchFile,
        ];
    }

    private function extractPatch(string $solution): ?string
    {
        $decoded = html_entity_decode($solution, ENT_QUOTES | ENT_HTML5);
        $decoded = preg_replace('/<br\\s*\\/?>/i', "\n", $decoded) ?? $decoded;
        $decoded = preg_replace('/<\/p>/i', "\n", $decoded) ?? $decoded;
        $decoded = strip_tags($decoded);
        $decoded = str_replace(["\r\n", "\r"], "\n", $decoded);

        if (preg_match('/```(?:patch|diff)\s*(.*?)```/is', $decoded, $matches) === 1) {
            $patch = trim($matches[1]);
            return $patch !== '' ? $patch."\n" : null;
        }

        if (preg_match('/(\*\*\* Begin Patch.*\*\*\* End Patch)/is', $decoded, $matches) === 1) {
            $patch = trim($matches[1]);
            return $patch !== '' ? $patch."\n" : null;
        }

        return null;
    }
}
