<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

class Berechtigungskategorie extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'beschreibung',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_berechtigungskategories', 'berechtigungskategorie_id', 'role_id');
    }
    public function permissions() {
        return $this->hasMany(Permission::class);
    }
}
