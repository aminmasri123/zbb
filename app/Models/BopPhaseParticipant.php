<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BopPhaseParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'bop_phase_schedule_id', 'personen_ist_schueler_id', 'class_name',
        'group_key', 'completion_status', 'completed_at', 'notes',
    ];

    protected $casts = ['completed_at' => 'datetime'];

    public function phase()
    {
        return $this->belongsTo(BopPhaseSchedule::class, 'bop_phase_schedule_id');
    }

    public function student()
    {
        return $this->belongsTo(PersonenIstSchueler::class, 'personen_ist_schueler_id');
    }
}
