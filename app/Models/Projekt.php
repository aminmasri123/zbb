<?php

namespace App\Models;

use App\Models\Abteilung;
use App\Models\Teilnehmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'kostenstelle',
        'abteilung_id',
        'beschreibung'
    ];
    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id', 'id');
    }


    public function teilnehmer()
    {
        return $this->belongsToMany(Teilnehmer::class, 'projekt_has_teilnehmers', 'projekt_id', 'teilnehmer_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_has_projekts', 'projekt_id', 'user_id');
    }

    public function bereiche()
    {
        return $this->belongsToMany(Bereich::class, 'projekt_has_bereiches', 'projekt_id', 'bereich_id');
    }

    public function projektzeitraume(): HasMany
    {
        return $this->hasMany(Projektzeitraum::class, 'projekt_id');
    }
}
