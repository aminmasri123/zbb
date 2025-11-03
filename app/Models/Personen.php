<?php

namespace App\Models;

use App\Models\User;
use App\Models\Brief;
use App\Models\Baenke;
use App\Models\Adresse;
use App\Models\Projekt;
use App\Models\Standort;
use App\Models\Zielgruppe;

use App\Models\Abschluesse;
use App\Models\Anwesenheiten;
use App\Models\PersonenHasNotizen;
use App\Models\ProjektHasPersonen;
use App\Models\PersonenHasAbschluesse;
use App\Models\PersonenHasSozialedaten;
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
    public function notizen(){
        return $this->hasMany(PersonenHasNotizen::class, 'person_id', 'id');
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

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id')
            ->using(ProjektHasPersonen::class) // <== Pivot Model verwenden
            ->withPivot('id', 'bemerkung', 'status')                    // <- damit du das Pivot Model findest
            ->as('pivotModel') ;                 // <- schöner Name für den Pivot-Zugriff
    }

    public function kontaktes()
    {
        return $this->morphMany(Kontakte::class, 'model');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function abschluesse(): BelongsToMany
    {
        return $this->belongsToMany(Abschluesse::class, 'personen_has_abschluesses', 'person_id', 'abschluss_id')
        ->using(PersonenHasAbschluesse::class) // <== Pivot Model verwenden
            ->withPivot('bezeichnung', 'start', 'end', 'id')                    // <- damit du das Pivot Model findest
            ->as('pivotModel');
    }

    public function sozialedaten(){
        return $this->hasOne(PersonenHasSozialedaten::class, 'person_id', 'id');
    }
    public function zielgruppen(): BelongsToMany
    {
        return $this->belongsToMany(Zielgruppe::class, 'personen_has_zielgruppes', 'person_id', 'zielgruppe_id');
    }




}
