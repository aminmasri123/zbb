<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adresse extends Model
{
    use HasFactory;
    protected $fillable = [
        'model_type',
        'model_id',
        'strasse',
        'hausnummer',
        'plz',
        'stadt',
        'land',
        'zusatzinfo',
    ];
}
