<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BopTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'bop_phase_schedule_id', 'schedule_date', 'slot_minutes', 'config', 'generated_by_user_id',
    ];

    protected $casts = [
        'schedule_date' => 'date:Y-m-d',
        'config' => 'array',
    ];

    public function phase()
    {
        return $this->belongsTo(BopPhaseSchedule::class, 'bop_phase_schedule_id');
    }

    public function entries()
    {
        return $this->hasMany(BopTimetableEntry::class)->orderBy('start_time')->orderBy('group_key');
    }
}
