<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasTeilnehmerLuv extends Model
{
    use HasFactory;

    protected $fillable = [
        'typ',
        'version',
        'status',
        'form_version',
        'projekt_person_id',
        'template_id',
        'ai_report_run_id',
        'von',
        'bis',
        'ausgangssituation',
        'zielvereinbarung',
        'qualifikationen',
        'payload',
        'source_snapshot',
        'created_by',
        'reviewed_by',
        'approved_by',
        'reviewed_at',
        'approved_at',
        'discussed_on',
        'consent_confirmed',
    ];

    protected $casts = [
        'von' => 'date',
        'bis' => 'date',
        'payload' => 'array',
        'source_snapshot' => 'array',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'discussed_on' => 'date',
        'consent_confirmed' => 'boolean',
    ];

    public function projektHasTeilnehmer()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id');
    }

    public function template()
    {
        return $this->belongsTo(ProjektLuvTemplate::class, 'template_id');
    }

    public function aiReportRun()
    {
        return $this->belongsTo(AiReportRun::class, 'ai_report_run_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
