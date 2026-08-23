<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiWorkspaceRun extends Model
{
    protected $fillable = [
        'user_id', 'run_uuid', 'task', 'instruction', 'source_metadata',
        'request_payload', 'title', 'content', 'citations', 'warnings',
        'status', 'progress_percent', 'error_code', 'error_message',
        'duration_seconds', 'started_at', 'completed_at',
    ];

    protected $hidden = ['request_payload', 'error_message'];

    protected $casts = [
        'source_metadata' => 'array',
        'request_payload' => 'encrypted:array',
        'citations' => 'array',
        'warnings' => 'array',
        'progress_percent' => 'integer',
        'duration_seconds' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
