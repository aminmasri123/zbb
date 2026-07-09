<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LagerArtikel extends Model
{
    use HasFactory;

    protected $table = 'lager_artikel';

    protected $fillable = [
        'name',
        'kategorie',
        'artikelnummer',
        'einheit',
        'bestand',
        'mindestbestand',
        'lagerort',
        'lieferant',
        'beschreibung',
        'aktiv',
    ];

    protected $casts = [
        'bestand' => 'decimal:2',
        'mindestbestand' => 'decimal:2',
        'aktiv' => 'boolean',
    ];

    public function reservierungen()
    {
        return $this->hasMany(LagerReservierung::class, 'lager_artikel_id');
    }

    public function bewegungen()
    {
        return $this->hasMany(LagerBewegung::class, 'lager_artikel_id');
    }

    public function aktiveReservierungen()
    {
        return $this->reservierungen()->where('status', LagerReservierung::STATUS_RESERVIERT);
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', true);
    }
}
