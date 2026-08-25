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
        'profil_id',
        'name',
        'tag',
        'beschreibung',
        'hoechstwert',
        'auswertbar',
        'auswertung_hervorheben',
        'ergebnis_typ',
        'berechnungsregel',
        'zeit_erfassen',
        'fehler_abzug',
        'berechnungs_config',
        'mindestwert',
        'sort_order',
        'aktiv',
    ];

    protected $casts = [
        'tag' => 'integer',
        'hoechstwert' => 'integer',
        'auswertbar' => 'boolean',
        'auswertung_hervorheben' => 'boolean',
        'zeit_erfassen' => 'boolean',
        'mindestwert' => 'float',
        'fehler_abzug' => 'float',
        'berechnungs_config' => 'array',
        'sort_order' => 'integer',
        'aktiv' => 'boolean',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function profil()
    {
        return $this->belongsTo(PotenzialanalyseProfil::class, 'profil_id');
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

    public function kompetenzZuordnungen()
    {
        return $this->hasMany(PotenzialanalyseUebungKompetenz::class, 'uebung_id')
            ->orderBy('merkmal');
    }
}
