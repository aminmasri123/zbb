<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BopTimetableEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'bop_timetable_id', 'group_key', 'type', 'title', 'bereich_id',
        'supervisor_person_id', 'start_time', 'end_time', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function timetable()
    {
        return $this->belongsTo(BopTimetable::class);
    }

    public function bereich()
    {
        return $this->belongsTo(Bereich::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Personen::class, 'supervisor_person_id');
    }
}
