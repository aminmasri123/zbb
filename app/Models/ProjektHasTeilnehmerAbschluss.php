<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasTeilnehmerAbschluss extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'projekt_has_person_id',
        'austritttypen_id',
        'ergebnisse_id',
        'verbleib_id',


    ];
}
