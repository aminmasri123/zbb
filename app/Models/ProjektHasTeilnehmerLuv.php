<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasTeilnehmerLuv extends Model
{
    use HasFactory;
     protected $fillable = [
        'typ',
        'projekt_person_id',
        'von',
        'bis',
        'ausgangssituation',
        'zielvereinbarung',
    ];

        protected $dates = [
        'von',
        'bis',
        ];
}
