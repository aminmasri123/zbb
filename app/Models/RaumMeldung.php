<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaumMeldung extends Model
{
    use HasFactory;

    protected $table = 'raum_meldungen';

    protected $fillable = [
        'raum_id',
        'projekt_id',
        'gruppe_id',
        'gemeldet_von_user_id',
        'gemeldet_von_personen_id',
        'zugewiesen_an_personen_id',
        'behoben_von_personen_id',
        'titel',
        'kategorie',
        'prioritaet',
        'status',
        'faellig_am',
        'beschreibung',
        'massnahme',
        'kosten',
        'interne_notiz',
        'erledigt_am',
        'behoben_am',
    ];

    protected $casts = [
        'faellig_am' => 'date',
        'erledigt_am' => 'datetime',
        'behoben_am' => 'datetime',
        'kosten' => 'decimal:2',
    ];

    public function raum()
    {
        return $this->belongsTo(Raeume::class, 'raum_id');
    }

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function gruppe()
    {
        return $this->belongsTo(Gruppe::class, 'gruppe_id');
    }

    public function gemeldetVon()
    {
        return $this->belongsTo(User::class, 'gemeldet_von_user_id');
    }

    public function gemeldetVonPerson()
    {
        return $this->belongsTo(Personen::class, 'gemeldet_von_personen_id');
    }

    public function zugewiesenAnPerson()
    {
        return $this->belongsTo(Personen::class, 'zugewiesen_an_personen_id');
    }

    public function behobenVonPerson()
    {
        return $this->belongsTo(Personen::class, 'behoben_von_personen_id');
    }
}
