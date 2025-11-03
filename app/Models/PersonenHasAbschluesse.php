<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PersonenHasAbschluesse extends Pivot //Model
{
    use HasFactory;
    protected $table = 'personen_has_abschluesses';


    protected $fillable = [
        'id',
        'person_id',
        'abschluss_id',
        'bezeichnung',
        'start',
        'end'

    ];

    protected $dates = [
        'start',
        'end'
    ];

}
