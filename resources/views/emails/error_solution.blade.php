<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ config('ai-code-orchestrator.mail_subject') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f6;font-family:Verdana,Arial,sans-serif;color:#222;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:24px 0;">
    <tr>
        <td align="center">
            <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                <tr>
                    <td style="padding:20px 24px;background:#1f2937;color:#fff;">
                        <h2 style="margin:0;font-size:18px;line-height:1.4;">
                            {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'System Error Detected' : 'Errore rilevato nel sistema' }}
                        </h2>
                        <p style="margin:6px 0 0;font-size:13px;opacity:0.85;">
                            {{ config('app.name', 'Laravel') }} · {{ now()->toDateTimeString() }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        @php
                            $environmentName = strtolower((string) app()->environment());
                            $isProductionEnv = str_contains($environmentName, 'production');
                            $applyToken = (string) config('ai-code-orchestrator.manual_report_token', '');
                            $applyUrl = route('ai-code-orchestrator.apply-solution', [
                                'report' => $report->id,
                                'token' => $applyToken,
                            ]);
                        @endphp

                        <h3 style="margin:0 0 12px;font-size:16px;">
                            {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Error Details' : 'Dettagli errore' }}
                        </h3>
                        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Report ID' : 'ID Report' }}</td>
                                <td style="padding:6px 0;">#{{ $report->id }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Created at' : 'Creato il' }}</td>
                                <td style="padding:6px 0;">{{ optional($report->created_at)->toDateTimeString() }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Status' : 'Stato' }}</td>
                                <td style="padding:6px 0;">{{ $report->status }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Message' : 'Messaggio' }}</td>
                                <td style="padding:6px 0;">{{ $report->message }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Class' : 'Classe' }}</td>
                                <td style="padding:6px 0;">{{ $report->exception_class }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'File' : 'File' }}</td>
                                <td style="padding:6px 0;">{{ $report->file }}:{{ $report->line }}</td>
                            </tr>
                            @php
                                $context = is_array($report->context ?? null) ? $report->context : [];
                                $offendingLine = $context['offending_line'] ?? null;
                            @endphp
                            @if($offendingLine)
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Offending line' : 'Riga incriminata' }}</td>
                                    <td style="padding:6px 0;">{{ $offendingLine }}</td>
                                </tr>
                            @endif
                            @if(isset($context['llama_file_index_count']) && $context['llama_file_index_count'] > 0)
                                <tr>
                                    <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Indexed files' : 'File indicizzati' }}</td>
                                    <td style="padding:6px 0;">{{ $context['llama_file_index_count'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">URL</td>
                                <td style="padding:6px 0;">{{ $report->url }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Method' : 'Metodo' }}</td>
                                <td style="padding:6px 0;">{{ $report->method }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ config('ai-code-orchestrator.ai.language') === 'en' ? 'User ID' : 'Utente ID' }}</td>
                                <td style="padding:6px 0;">{{ $report->user_id }}</td>
                            </tr>
                        </table>
                        <hr style="border:0;border-top:1px solid #e5e7eb;margin:16px 0;">
                        <h3 style="margin:0 0 12px;font-size:16px;">
                            {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'AI Suggested Fix' : 'Soluzione suggerita dall’AI' }}
                        </h3>
                        @php
                            $solution = (string) ($report->ai_solution ?? '');
                            $isHtml = $solution !== strip_tags($solution);
                        @endphp
                        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;font-size:13px;">
                            {!! $isHtml ? $solution : nl2br(e($solution)) !!}
                        </div>

                        @if(! $isProductionEnv)
                            <div style="margin:16px 0 0;padding:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:6px;">
                                <strong style="font-size:13px;color:#92400e;">
                                    {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Non-production mode' : 'Modalita non produzione' }}
                                </strong>
                                <p style="margin:8px 0 0;font-size:13px;color:#78350f;">
                                    {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Apply the AI patch directly from this report.' : 'Applica la patch AI direttamente da questo report.' }}
                                </p>
                                @if($applyToken !== '')
                                    <a href="{{ $applyUrl }}"
                                       style="display:inline-block;margin-top:10px;padding:9px 14px;border-radius:6px;background:#1f2937;color:#fff;text-decoration:none;font-size:13px;">
                                        {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Apply AI Fix' : 'Applica Fix AI' }}
                                    </a>
                                @else
                                    <p style="margin:10px 0 0;font-size:12px;color:#991b1b;">
                                        {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Missing manual report token: action disabled.' : 'Token manual report mancante: azione disabilitata.' }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px;background:#f9fafb;color:#6b7280;font-size:12px;">
                        {{ config('ai-code-orchestrator.ai.language') === 'en' ? 'Generated automatically by the AI Code Orchestrator.' : 'Generato automaticamente da AI Code Orchestrator.' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
