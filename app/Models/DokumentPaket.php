<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumentPaket extends Model
{
    use HasFactory;

    protected $table = 'dokument_pakete';

    protected $fillable = [
        'name',
        'beschreibung',
        'aktiv',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
    ];

    public function dokumente()
    {
        return $this->belongsToMany(
            Dokumente::class,
            'dokument_paket_has_dokumentes',
            'dokument_paket_id',
            'dokument_id'
        )
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderBy('dokumentes.name');
    }

    public function projekte()
    {
        return $this->belongsToMany(
            Projekt::class,
            'projekt_has_dokument_paketes',
            'dokument_paket_id',
            'projekt_id'
        );
    }
}
