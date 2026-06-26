<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'project_id',
        'team_id',
        'name',
        'background_color',
        'text_color',
        'visibility',
    ];

    public function events()
    {
        return $this->hasMany(AppCalendarEvent::class, 'calendar_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }
}
