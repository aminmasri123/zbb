<?php

namespace App\Models;

use App\Models\Fahrten;
use App\Models\Personen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Abrechnungen extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'monat',
        'jahr',
        'gesamtkosten',
        'status',
        'auszahldatum',
    ];

    public function person()
    {
        return $this->belongsTo(Personen::class);
    }

    public function fahrten()
    {
        return $this->belongsToMany(Fahrten::class, 'abrechnung_has_fahrtens');
    }
}
