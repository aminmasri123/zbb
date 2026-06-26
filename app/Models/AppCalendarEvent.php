<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppCalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'calendar_id',
        'project_id',
        'team_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'include_weekends',
        'excluded_dates',
        'location',
        'color',
        'background_color',
        'text_color',
        'visibility',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'include_weekends' => 'boolean',
        'excluded_dates' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function calendar()
    {
        return $this->belongsTo(AppCalendar::class, 'calendar_id');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }
}
