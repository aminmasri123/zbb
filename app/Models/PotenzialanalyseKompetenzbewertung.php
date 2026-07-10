<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseKompetenzbewertung extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_kompetenzbewertungen';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'user_id',
        'typ',
        'merkmal',
        'bewertung',
        'bemerkung',
    ];

    protected $casts = [
        'bewertung' => 'integer',
    ];
}
