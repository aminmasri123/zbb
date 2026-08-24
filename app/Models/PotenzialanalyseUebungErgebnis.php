<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseUebungErgebnis extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_uebung_ergebnisse';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'uebung_id',
        'user_id',
        'punkte',
        'fehler',
        'berechnete_punkte',
        'maximalpunkte_snapshot',
        'fehler_abzug_snapshot',
        'berechnungs_snapshot',
        'zeit',
    ];

    protected $casts = [
        'punkte' => 'integer',
        'fehler' => 'integer',
        'berechnete_punkte' => 'float',
        'maximalpunkte_snapshot' => 'float',
        'fehler_abzug_snapshot' => 'float',
        'berechnungs_snapshot' => 'array',
        'zeit' => 'integer',
    ];

    public function uebung()
    {
        return $this->belongsTo(PotenzialanalyseUebung::class, 'uebung_id');
    }
}
