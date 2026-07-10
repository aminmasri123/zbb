<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseKriterium extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_kriterien';

    protected $fillable = [
        'uebung_id',
        'name',
        'beschreibung',
        'skala_min',
        'skala_max',
        'sort_order',
        'aktiv',
    ];

    protected $casts = [
        'skala_min' => 'integer',
        'skala_max' => 'integer',
        'sort_order' => 'integer',
        'aktiv' => 'boolean',
    ];

    public function uebung()
    {
        return $this->belongsTo(PotenzialanalyseUebung::class, 'uebung_id');
    }
}
