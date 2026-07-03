<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dokumente extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'typ',
        'kontext',
        'einsatzbereich',
        'ausgabeformate',
        'version',
        'dateipfad',
        'dateipfadName',
        'beschreibung',
        'aktiv',
    ];

    protected $casts = [
        'ausgabeformate' => 'array',
        'aktiv' => 'boolean',
    ];

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_dokumentes', 'dokument_id', 'projekt_id')
            ->withPivot(['gruppen_export', 'serienbrief', 'sort_order']);
    }

    public function kategorien()
    {
        return $this->belongsToMany(DokumentKategorie::class, 'dokument_has_kategories', 'dokument_id', 'dokument_kategorie_id')
            ->withPivot(['gruppen_export', 'serienbrief']);
    }

    public function bereiche()
    {
        return $this->belongsToMany(Bereich::class, 'dokument_has_bereiches', 'dokument_id', 'bereich_id')
            ->orderBy('bereiches.name');
    }

}
