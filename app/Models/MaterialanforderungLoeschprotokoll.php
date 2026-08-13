<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialanforderungLoeschprotokoll extends Model
{
    protected $fillable = [
        'materialanforderung_id',
        'projekt_id',
        'ersteller_id',
        'geloescht_von_id',
        'status',
        'bestellnummer',
        'endsumme',
        'begruendung',
        'snapshot',
        'geloescht_am',
    ];

    protected $casts = [
        'endsumme' => 'decimal:2',
        'snapshot' => 'array',
        'geloescht_am' => 'datetime',
    ];

    public function geloeschtVon()
    {
        return $this->belongsTo(User::class, 'geloescht_von_id');
    }
}
