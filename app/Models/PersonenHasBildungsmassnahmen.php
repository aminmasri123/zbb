<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenHasBildungsmassnahmen extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'projekt_person_id',
        'typ',
        'placement_type',
        'traeger',
        'host_project_id',
        'supervisor_person_id',
        'host_address',
        'department',
        'internship_kind',
        'occupation',
        'attendance_weekday',
        'contact_name',
        'contact_email',
        'contact_phone',
        'start',
        'end',
        'weekly_hours',
        'next_follow_up_at',
        'bemerkung',
        'objective',
        'activities',
        'assessment',
        'result',
        'archived_at',
        'status',
    ];

    public function projektTeilnahme()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id');
    }

    public function participant()
    {
        return $this->belongsTo(Personen::class, 'person_id');
    }

    public function hostProject()
    {
        return $this->belongsTo(Projekt::class, 'host_project_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Personen::class, 'supervisor_person_id');
    }

    protected $date = [
        'start',
        'end',
    ];

    protected $casts = [
        'start' => 'date',
        'end' => 'date',
        'next_follow_up_at' => 'date',
        'archived_at' => 'datetime',
        'weekly_hours' => 'integer',
    ];

    public function statusHistory()
    {
        return $this->hasMany(EducationMeasureStatusHistory::class, 'education_measure_id')->oldest();
    }
}
