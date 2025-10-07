<?php

namespace App\Models;

use App\Models\Ansprechpartner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'beschreibung'
    ];


    public function ansprechpartner(): HasMany
    {
        return $this->hasMany(Ansprechpartner::class, 'partner_id', 'id');
    }

     public function partnerschaftstypens(): BelongsToMany
    {
        return $this->belongsToMany(Partnerschaftstypen::class, 'partner_has_partnerschaftstypens', 'partner_id', 'partnerschaftstypen_id');
    }
}
