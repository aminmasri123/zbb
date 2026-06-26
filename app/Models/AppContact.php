<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'project_id',
        'team_id',
        'name',
        'organization',
        'role',
        'email',
        'phone',
        'notes',
        'visibility',
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
