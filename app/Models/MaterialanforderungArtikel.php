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

    // Berechne Gesamtpreis inkl. MwSt
    public function gesamtMitMwst(): float
    {
        return $this->gesamtpreis + ($this->gesamtpreis * $this->mwst / 100);
    }
}
