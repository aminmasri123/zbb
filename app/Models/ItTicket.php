<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItTicket extends Model
{
    use HasFactory;

    public const STATUSES = [
        'neu',
        'gesichtet',
        'geplant',
        'in_bearbeitung',
        'wartet_auf_rueckmeldung',
        'wartet_auf_extern',
        'geloest',
        'geschlossen',
    ];

    public const PRIORITIES = [
        'niedrig',
        'normal',
        'hoch',
        'kritisch',
    ];

    public const CATEGORIES = [
        'hardware',
        'software',
        'netzwerk',
        'drucker',
        'telefon',
        'zugang',
        'sicherheit',
        'sonstiges',
    ];

    protected $table = 'it_tickets';

    protected $fillable = [
        'ticket_nr',
        'standort_id',
        'geraet_id',
        'gemeldet_von_user_id',
        'gemeldet_von_personen_id',
        'betroffene_personen_id',
        'zugewiesen_an_personen_id',
        'geloest_von_personen_id',
        'titel',
        'kategorie',
        'prioritaet',
        'status',
        'raum',
        'kontakt',
        'beschreibung',
        'planung',
        'loesung',
        'interne_notiz',
        'faellig_am',
        'geplant_am',
        'begonnen_at',
        'geloest_at',
        'geschlossen_at',
    ];

    protected $casts = [
        'faellig_am' => 'date',
        'geplant_am' => 'datetime',
        'begonnen_at' => 'datetime',
        'geloest_at' => 'datetime',
        'geschlossen_at' => 'datetime',
    ];

    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }

    public function geraet()
    {
        return $this->belongsTo(Geraet::class, 'geraet_id');
    }

    public function gemeldetVon()
    {
        return $this->belongsTo(User::class, 'gemeldet_von_user_id');
    }

    public function gemeldetVonPerson()
    {
        return $this->belongsTo(Personen::class, 'gemeldet_von_personen_id');
    }

    public function betroffenePerson()
    {
        return $this->belongsTo(Personen::class, 'betroffene_personen_id');
    }

    public function zugewiesenAnPerson()
    {
        return $this->belongsTo(Personen::class, 'zugewiesen_an_personen_id');
    }

    public function geloestVonPerson()
    {
        return $this->belongsTo(Personen::class, 'geloest_von_personen_id');
    }

    public function scopeOffen($query)
    {
        return $query->whereNotIn('status', ['geloest', 'geschlossen']);
    }
}
