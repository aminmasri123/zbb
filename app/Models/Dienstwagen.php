<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Standort;
use App\Models\Dienstwagenfahrtenbuch;
use Illuminate\Database\Eloquent\Model;
use App\Models\Dienstwagenkostenaufzeichnungen;
use App\Models\Dienstwagenwartungsaufzeichnungen;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dienstwagen extends Model
{
    use HasFactory;

    protected $fillable = [
        'typ',
        'kennzeichen',
        'marke',
        'modell',
        'baujahr',
        'kraftstoffart',
        'kilometerstand',
        'standort_id',
        'status',
        'naechste_wartung',
    ];

    public function scopeAktiv($query)
    {
        return $query->where('status','!=', 'passiv')->where('status','!=', 'verkäuft');
    }

    public function fahrer()
    {
        return $this->belongsToMany(Personen::class, 'dienstwagen_has_personens', 'dienstwagen_id', 'person_id');
    }

    public function wartungen()
    {
        return $this->hasMany(Dienstwagenwartungsaufzeichnungen::class);
    }

    public function kostanaufzeichnungen()
    {
        return $this->hasMany(Dienstwagenkostenaufzeichnungen::class);
    }

    public function fahrten()
    {
        return $this->hasMany(Dienstwagenfahrtenbuch::class);
    }

    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }
}
