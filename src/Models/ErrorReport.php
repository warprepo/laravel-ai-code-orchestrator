<?php

namespace Warp\LaravelAiCodeOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorReport extends Model
{
    protected $table = 'ai_error_reports';

    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'url',
        'method',
        'user_id',
        'context',
        'ai_solution',
        'status',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
