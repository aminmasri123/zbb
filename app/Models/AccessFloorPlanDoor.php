<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessFloorPlanDoor extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_floor_plan_id',
        'access_door_id',
        'x_percent',
        'y_percent',
        'rotation_degrees',
    ];

    protected $casts = [
        'x_percent' => 'float',
        'y_percent' => 'float',
        'rotation_degrees' => 'float',
    ];

    public function floorPlan()
    {
        return $this->belongsTo(AccessFloorPlan::class, 'access_floor_plan_id');
    }

    public function door()
    {
        return $this->belongsTo(AccessDoor::class, 'access_door_id');
    }
}
