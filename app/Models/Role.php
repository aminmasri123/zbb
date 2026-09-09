<?php

namespace App\Models;

use App\Models\Berechtigungskategorie;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends SpatieRole
{
    use HasFactory;
    protected $appends = ['display_name'];

    public function getDisplayNameAttribute($value): string
    {
        $value ??= $this->attributes['display_name'] ?? null;
        return filled($value) ? $value : (string) $this->name;
    }
    protected $fillable = [
        'color',
        'name',
        'display_name',
        'guard_name',
        'id',
    ];
    public function berechtigungskategories(){
        return $this->belongsToMany(
            Berechtigungskategorie::class,
            'role_berechtigungskategories',
            'role_id',
            'berechtigungskategorie_id'
        );
    }


}
