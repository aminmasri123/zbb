<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function doors()
    {
        return $this->belongsToMany(AccessDoor::class, 'access_profile_door');
    }

    public function requests()
    {
        return $this->hasMany(AccessRequest::class);
    }
}
