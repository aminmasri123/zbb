<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'scope_key',
        'location_id',
        'enabled',
        'settings',
        'activated_by_user_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(SystemModule::class, 'module_id');
    }

    public function location()
    {
        return $this->belongsTo(Standort::class, 'location_id');
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }
}
