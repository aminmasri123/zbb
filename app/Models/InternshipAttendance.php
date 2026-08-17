<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternshipAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'education_measure_id',
        'attendance_date',
        'status',
        'planned_minutes',
        'actual_minutes',
        'note',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'planned_minutes' => 'integer',
        'actual_minutes' => 'integer',
    ];

    public function internship()
    {
        return $this->belongsTo(PersonenHasBildungsmassnahmen::class, 'education_measure_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
