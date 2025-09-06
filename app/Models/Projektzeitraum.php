<?php

namespace App\Models;

use App\Models\Projekt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Projektzeitraum extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'projekt_id',
        'antragsdatum',
        'starttermin',
        'anfangsdatum',
        'endtermin',
        'enddatum',
    ];
    protected $dates = [
        'antragsdatum',
        'starttermin',
        'anfangsdatum',
        'endtermin',
        'enddatum',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class);
    }
}
