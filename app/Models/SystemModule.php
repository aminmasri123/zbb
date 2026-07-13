<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemModule extends Model
{
    use HasFactory;

    protected $table = 'modules';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'is_system_module',
        'is_enforced',
        'supports_location_scope',
        'visible_in_settings',
        'default_enabled',
        'status',
    ];

    protected $casts = [
        'is_system_module' => 'boolean',
        'is_enforced' => 'boolean',
        'supports_location_scope' => 'boolean',
        'visible_in_settings' => 'boolean',
        'default_enabled' => 'boolean',
    ];

    public function assignments()
    {
        return $this->hasMany(ModuleAssignment::class, 'module_id');
    }
}
