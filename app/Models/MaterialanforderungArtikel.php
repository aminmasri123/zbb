<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialanforderungArtikel extends Model
{
    use HasFactory;
    protected $fillable = [
        'anforderung_id',
        'pos',
        'artikel',
        'stueck',
        'gelieferte_menge',
        'art_nr',
        'einzelpreis',
        'gesamtpreis',
        'mwst',
        'link',
    ];

    public function anforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'anforderung_id');
    }

    public function kommentare()
    {
        return $this->hasMany(MaterialanforderungKommentar::class, 'artikel_id');
    }

    // Berechne Gesamtpreis inkl. MwSt
    public function gesamtMitMwst(): float
    {
        return $this->gesamtpreis + ($this->gesamtpreis * $this->mwst / 100);
    }
}
