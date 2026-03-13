<?php

namespace App\Models;

use App\Models\Geraet;
use App\Models\Personen;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Geraetausgabe extends Model
{
    use HasFactory;

     use HasFactory;
    protected $fillable = [
        'ausgabescheinNr',
        'ausleiher_id',
        'projekte_id',
        'ausgabe',
    ];
    protected $dates = [
        'ausgabe',
    ];


    //Bearbeitet

   public function ausleiher(): BelongsTo
    {
        return $this->belongsTo(Personen::class, 'ausleiher_id')->where('typ', 'mitarbeiter');
    }
    public function geraete()
    {
        return $this->belongsToMany(Geraet::class, 'geraet_has_ausgabes', 'ausgabe_id', 'geraet_id');
    }

    public function projekte(): BelongsTo
    {
        return $this->belongsTo(Projekt::class);
    }
    //zu erledigen

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function rueckgabe(): HasOne
    {
        return $this->hasOne(Rueckgabe::class);
    }


}
