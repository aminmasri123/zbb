<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseUebungKompetenz extends Model
{
    protected $table = 'potenzialanalyse_uebung_kompetenzen';

    protected $fillable = [
        'uebung_id',
        'merkmal',
        'gewichtung',
        'aktiv',
    ];

    protected $casts = [
        'gewichtung' => 'float',
        'aktiv' => 'boolean',
    ];

    public function uebung()
    {
        return $this->belongsTo(PotenzialanalyseUebung::class, 'uebung_id');
    }
}
