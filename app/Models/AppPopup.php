<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppPopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'project_id',
        'team_id',
        'title',
        'message',
        'level',
        'starts_at',
        'ends_at',
        'active',
        'visibility',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }
}
