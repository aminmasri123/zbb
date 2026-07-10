<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseUebung extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_uebungen';

    protected $fillable = [
        'projekt_id',
        'name',
        'tag',
        'beschreibung',
        'hoechstwert',
        'auswertbar',
        'sort_order',
        'aktiv',
    ];

    protected $casts = [
        'tag' => 'integer',
        'hoechstwert' => 'integer',
        'auswertbar' => 'boolean',
        'sort_order' => 'integer',
        'aktiv' => 'boolean',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function kriterien()
    {
        return $this->hasMany(PotenzialanalyseKriterium::class, 'uebung_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function ergebnisse()
    {
        return $this->hasMany(PotenzialanalyseUebungErgebnis::class, 'uebung_id');
    }
}
