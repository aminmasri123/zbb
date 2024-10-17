<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Abteilung extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function abteilungsassistente(): HasMany
    {
        return $this->hasMany(Abteilungsassistent::class, 'abteilung_id');
    }

    /*public function projekte(): HasMany
    {
        return $this->hasMany(Projekte::class);
    }*/



}
