<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Zeitraum extends Pivot //Model
{

    use HasFactory;
    protected $table = 'zeitraums'; // ✅ wichtig, da Pivot keine Tabelle rät

    public $timestamps = false;

    protected $fillable = [
        'antragsdatum',
        'starttermin',
        'startzeit',
        'endtermin',
        'endzeit',
        'anfangsdatum',
        'enddatum',
        'model_type',
        'model_id'
    ];
    protected $dates = [
        'antragsdatum',
        'starttermin',
        'endtermin',
        'anfangsdatum',
        'enddatum',
        'startzeit',
        'endzeit'
    ];

    protected $casts = [
        'antragsdatum' => 'date',
        'starttermin' => 'date',
        'startzeit' => 'string',
        'endtermin' => 'date',
        'endzeit' => 'string',
        'anfangsdatum' => 'date',
        'enddatum' => 'date',
    ];
}
