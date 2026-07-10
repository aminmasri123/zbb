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
        'zeit',
    ];

    protected $casts = [
        'punkte' => 'integer',
        'zeit' => 'integer',
    ];

    public function uebung()
    {
        return $this->belongsTo(PotenzialanalyseUebung::class, 'uebung_id');
    }
}
