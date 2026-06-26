<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'owner_user_id',
        'project_id',
        'team_id',
        'type',
        'name',
        'original_name',
        'path',
        'mime_type',
        'size',
        'visibility',
        'notes',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function shares()
    {
        return $this->morphMany(AppShare::class, 'shareable');
    }
}
