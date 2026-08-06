<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BopPhaseSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'bop_run_id', 'phase_type', 'dates', 'scope_type', 'selected_classes',
        'days_per_class', 'class_date_assignments', 'part_date_assignments',
        'group_mode', 'group_count', 'supervisor_person_id', 'bereich_id',
        'raum_id', 'start_time', 'end_time', 'generate_groups',
        'publish_to_calendar', 'calendar_event_id', 'einteilung_setting_id', 'notes',
    ];

    protected $casts = [
        'dates' => 'array',
        'selected_classes' => 'array',
        'class_date_assignments' => 'array',
        'part_date_assignments' => 'array',
        'generate_groups' => 'boolean',
        'publish_to_calendar' => 'boolean',
    ];

    public function run()
    {
        return $this->belongsTo(BopRun::class, 'bop_run_id');
    }

    public function participants()
    {
        return $this->hasMany(BopPhaseParticipant::class);
    }

    public function calendarEvent()
    {
        return $this->belongsTo(AppCalendarEvent::class);
    }

    public function groups()
    {
        return $this->hasMany(Gruppe::class);
    }
}
