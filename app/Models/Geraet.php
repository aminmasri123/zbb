<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geraet extends Model
{
    use HasFactory;
    protected $table = 'geraets';
    protected $fillable = [
        'sn',
        'productID',
        'inventarnummer',
        'zustand',
        'status',
        'geraet',
        'kategorie',
        'imLager',
        'standort_id',
        'verantwortliche_personen_id',
        'raum',
        'hersteller',
        'modell',
        'ip_adresse',
        'mac_adresse',
        'betriebssystem',
        'baujahr',
        'garantiefrist',
        'letzte_wartung_am',
        'naechste_wartung_am',
        'notiz',
        'verfuegbarkeit',
    ];

    protected $casts = [
        'baujahr' => 'date',
        'garantiefrist' => 'date',
        'letzte_wartung_am' => 'date',
        'naechste_wartung_am' => 'date',
        'verfuegbarkeit' => 'boolean',
    ];

    public function ausgaben()
    {
        return $this->belongsToMany(Geraetausgabe::class, 'geraet_has_ausgabes', 'geraet_id', 'ausgabe_id');
    }
    public function rueckgaben()
    {
        return $this->belongsToMany(Geraetrueckgabe::class, 'geraet_has_rueckgabes', 'geraet_id', 'rueckgabe_id');
    }

    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }

    public function verantwortlichePerson()
    {
        return $this->belongsTo(Personen::class, 'verantwortliche_personen_id');
    }

    public function itTickets()
    {
        return $this->hasMany(ItTicket::class, 'geraet_id');
    }


}
