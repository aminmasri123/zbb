<?php

namespace App\Models;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\Personen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Geraetrueckgabe extends Model
{
    use HasFactory;
    protected $fillable = [
        'rueckgabescheinNr',
        'ausleiher_id',
        'ausgabe_id',
        'rueckgabe',
        'ablageort'
    ];
    protected $dates = [
        'rueckgabe',
    ];

    public function ausgabe(): BelongsTo
    {
        return $this->belongsTo(Geraetausgabe::class);
    }
    public function geraete()
    {
        return $this->belongsToMany(Geraet::class, 'geraet_has_rueckgabes', 'rueckgabe_id', 'geraet_id');
    }
    public function ausleiher(): BelongsTo
    {
        return $this->belongsTo(Personen::class);
    }
}
