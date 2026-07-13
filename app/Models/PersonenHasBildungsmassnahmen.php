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
        'traeger',
        'contact_name',
        'contact_email',
        'contact_phone',
        'start',
        'end',
        'weekly_hours',
        'next_follow_up_at',
        'bemerkung',
        'objective',
        'result',
        'archived_at',
        'status'
    ];

    public function projektTeilnahme()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id');
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
