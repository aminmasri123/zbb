<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumentKategorie extends Model
{
    use HasFactory;

    protected $table = 'dokument_kategories';

    protected $fillable = [
        'name',
        'beschreibung',
    ];

    public function dokumente()
    {
        return $this->belongsToMany(Dokumente::class, 'dokument_has_kategories', 'dokument_kategorie_id', 'dokument_id')
            ->withPivot(['gruppen_export', 'serienbrief'])
            ->orderBy('dokumentes.name');
    }

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_dokument_kategories', 'dokument_kategorie_id', 'projekt_id');
    }
}
