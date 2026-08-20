<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessDoor extends Model
{
    use HasFactory;

    protected $fillable = [
        'standort_id',
        'room_from_id',
        'room_to_id',
        'name',
        'code',
        'external_reference',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }

    public function roomFrom()
    {
        return $this->belongsTo(Raeume::class, 'room_from_id');
    }

    public function roomTo()
    {
        return $this->belongsTo(Raeume::class, 'room_to_id');
    }

    public function profiles()
    {
        return $this->belongsToMany(AccessProfile::class, 'access_profile_door');
    }

    public function floorPlanPlacements()
    {
        return $this->hasMany(AccessFloorPlanDoor::class);
    }

    public function requiredForRooms()
    {
        return $this->belongsToMany(Raeume::class, 'access_door_room_requirements', 'access_door_id', 'raum_id')
            ->withTimestamps();
    }
}
