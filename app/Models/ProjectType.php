<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'module_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(SystemModule::class, 'module_id');
    }

    public function projects()
    {
        return $this->hasMany(Projekt::class, 'project_type_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (ProjectType $projectType) {
            $projectType->projects()->update(['project_type_id' => null]);
        });
    }
}
