<?php

namespace App\Models;

use App\Models\User;
use App\Models\Brief;
use App\Models\Baenke;
use App\Models\Adresse;
use App\Models\Projekt;
use App\Models\Standort;
use App\Models\Anwesenheiten;

use App\Models\ProjektHasPersonen;
use Illuminate\Database\Eloquent\Model;
use App\Models\PersonenHasAnwesenheiten;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Personen extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'vorname',
        'nachname',
        'geschlecht',
        'aktiv',
        'typ'
    ];
    protected $date = [
        'geburtsdatum',
    ];

    public function anwesenheiten(){
        return $this->hasMany(PersonenHasAnwesenheiten::class, 'personen_id', 'id');
    }

    public function scopeTeilnehmer($query)
    {
        return $query->where('typ', 'teilnehmer');
    }

    public function scopeMitarbeiter($query)
    {
        return $query->where('typ', 'mitarbeiter');
    }

    public function scopeActive($query)
    {
        return $query->where('aktiv', 1);
    }

    public function standorte(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_has_personens', 'personen_id', 'standort_id');
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
        return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id');
    } */
        /* public function projekte()
        {
            return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id')
                ->using(ProjektHasPersonen::class) // Pivotmodell aktivieren
                ->as('pivotModel'); // schöner Name
        } */

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id')
            ->using(ProjektHasPersonen::class) // <== Pivot Model verwenden
            ->withPivot('id')                    // <- damit du das Pivot Model findest
            ->as('pivotModel');                  // <- schöner Name für den Pivot-Zugriff
    }

    public function kontaktes()
    {
        return $this->morphMany(Kontakte::class, 'model');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
