<?php

namespace App\Models;

use App\Models\Baenke;
use App\Models\Adresse;
use App\Models\Projekt;
use App\Models\Kontakte;
use App\Models\Standort;
use App\Models\ProjektHasTeilnehmer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Teilnehmer extends Model
{
    use HasFactory;


    protected $fillable = [
        'id',
        'vorname',
        'nachname',
        'geschlecht',
        'current_team_id',

    ];

     public function standorte(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_has_teilnehmers', 'teilnehmer_id', 'standort_id');
    }

    public function adresses()
    {
        return $this->morphMany(Adresse::class, 'model');
    }

    public function baenke()
    {
        return $this->morphMany(Baenke::class, 'model');
    }

    /* public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_teilnehmers', 'teilnehmer_id', 'projekt_id');
    } */

   public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_teilnehmers', 'teilnehmer_id', 'projekt_id')
            ->using(ProjektHasTeilnehmer::class) // <== Pivot Model verwenden
            ->withPivot('id')                    // <- damit du das Pivot Model findest
            ->as('pivotModel');                  // <- schöner Name für den Pivot-Zugriff
    }


    public function kontaktes()
    {
        return $this->morphMany(Kontakte::class, 'model');
    }


}
