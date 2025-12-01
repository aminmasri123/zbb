<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenHasBildungsmassnahmen extends Model
{
    use HasFactory;
protected $fillable = [
        'person_id',
        'typ',
        'traeger',
        'start',
        'end',
        'bemerkung',
        'status'
    ];

    protected $date = [
        'start',
        'end',
    ];
    protected $casts = [
        'start' => 'date',
        'end' => 'date',
    ];
}
