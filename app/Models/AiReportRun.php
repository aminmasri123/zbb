<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiReportRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'user_id',
        'project_id',
        'participant_id',
        'report_type',
        'luv_type',
        'template_id',
        'from_date',
        'until_date',
        'request',
        'status',
        'progress_percent',
        'report',
        'error_code',
        'error_message',
        'duration_seconds',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'report' => 'array',
        'from_date' => 'date',
        'until_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'progress_percent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjektLuvTemplate::class, 'template_id');
    }
}
