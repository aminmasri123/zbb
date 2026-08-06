<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppCalendarEventAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'assigned_by_user_id',
        'access_level',
        'response_required',
        'response',
        'response_note',
        'responded_at',
    ];

    protected $casts = [
        'response_required' => 'boolean',
        'responded_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(AppCalendarEvent::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
