<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessFloorPlanRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_floor_plan_id',
        'raum_id',
        'x_percent',
        'y_percent',
        'width_percent',
        'height_percent',
    ];

    protected $casts = [
        'x_percent' => 'float',
        'y_percent' => 'float',
        'width_percent' => 'float',
        'height_percent' => 'float',
    ];

    public function floorPlan()
    {
        return $this->belongsTo(AccessFloorPlan::class, 'access_floor_plan_id');
    }

    public function room()
    {
        return $this->belongsTo(Raeume::class, 'raum_id');
    }
}
