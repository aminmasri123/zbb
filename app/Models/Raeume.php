<?php

namespace App\Models;

use App\Models\Standort;
use App\Models\Personen;
use App\Models\RaumMeldung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Raeume extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'standort_id',
        'beschreibung',
        'id',
        'kapazitaet',
        'typ',
        'parent_id',
        'belegungsart',
        'standard_personen_id',
        'aktiv',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
    ];


    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function standardPerson()
    {
        return $this->belongsTo(Personen::class, 'standard_personen_id');
    }

    public function meldungen()
    {
        return $this->hasMany(RaumMeldung::class, 'raum_id')->latest();
    }
}
