<?php

namespace App\Models;

use App\Models\MaterialanforderungArtikel;
use App\Models\MaterialanforderungVergabevermerk;
use App\Models\Personen;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materialanforderung extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'kostenstelle',
        'status',
        'gesamtpreis',
        'endsumme',
        'bemerkungen',
        'ersteller_id',
    ];
    public function vergabevermerke()
    {
        return $this->hasMany(MaterialanforderungVergabevermerk::class, 'anforderung_id');
    }
    // Beziehungen
    public function besteller()
    {
        return $this->hasOne(Personen::class, 'id', 'ersteller_id');
    }

    public function artikeln()
    {
        return $this->hasMany(MaterialanforderungArtikel::class, 'anforderung_id');
    }

    

    public function genehmigungen()
    {
        return $this->hasMany(MaterialanforderungGenehmigung::class, 'anforderung_id');
    }

    // Berechne Gesamtsumme inkl. MwSt
    public function berechneEndsumme(): float
    {
        return $this->positionen->sum(function ($position) {
            return $position->gesamtpreis + ($position->gesamtpreis * $position->mwst / 100);
        });
    }

    // Materialanforderung.php
    public function projekt()
    {
        return $this->belongsTo(Projekt::class);
    }
}
