<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppTaskWorkflowTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'project_id',
        'team_id',
        'name',
        'description',
        'visibility',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function steps()
    {
        return $this->hasMany(AppTaskWorkflowStep::class, 'template_id')->orderBy('sort_order');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }
}
