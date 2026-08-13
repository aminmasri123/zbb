<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialanforderungVergabevermerk extends Model
{
    use HasFactory;
    protected $fillable = [
        'anforderung_id',
        'kurzbeschreibung',
        'lieferung_art',
        'begruendung',
        'begruendung_optionen',
        'lieferant',
        'lieferung_option',
        'lieferadresse',
        'bestellnummer',
    ];

    protected $casts = [
        'begruendung_optionen' => 'array',
    ];

    public function anforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'anforderung_id');
    }
}
