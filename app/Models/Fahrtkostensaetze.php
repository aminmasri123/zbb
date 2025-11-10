<?php

namespace App\Models;

use App\Models\Fahrtarten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fahrtkostensaetze extends Model
{
    use HasFactory;

     protected $fillable = [
        'fahrtart_id',
        'rechentyp',
        'satz',
        'gueltig_ab',
        'gueltig_bis',
        'bemerkung',
    ];

    public function fahrtart()
    {
        return $this->belongsTo(Fahrtarten::class, 'fahrtart_id');
    }


}
