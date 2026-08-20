<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessFloorPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'standort_id',
        'floor_label',
        'name',
        'image_path',
        'original_name',
        'mime_type',
        'image_width',
        'image_height',
        'active',
    ];

    protected $casts = [
        'image_width' => 'integer',
        'image_height' => 'integer',
        'active' => 'boolean',
    ];

    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }

    public function roomPlacements()
    {
        return $this->hasMany(AccessFloorPlanRoom::class);
    }

    public function doorPlacements()
    {
        return $this->hasMany(AccessFloorPlanDoor::class);
    }
}
