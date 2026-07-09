<?php

namespace App\Models;

use App\Models\Standort;
use App\Models\Personen;
use App\Models\Gruppe;
use App\Models\RaumBuchung;
use App\Models\RaumMeldung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Raeume extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'raumnummer',
        'etage',
        'standort_id',
        'beschreibung',
        'id',
        'kapazitaet',
        'flaeche_qm',
        'typ',
        'parent_id',
        'belegungsart',
        'status',
        'standard_personen_id',
        'verantwortliche_personen_id',
        'aktiv',
        'buchbar',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'buchbar' => 'boolean',
        'flaeche_qm' => 'decimal:2',
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

    public function verantwortlichePerson()
    {
        return $this->belongsTo(Personen::class, 'verantwortliche_personen_id');
    }

    public function meldungen()
    {
        return $this->hasMany(RaumMeldung::class, 'raum_id')->latest();
    }

    public function buchungen()
    {
        return $this->hasMany(RaumBuchung::class, 'raum_id')->orderBy('start_at');
    }

    public function gruppen()
    {
        return $this->hasMany(Gruppe::class, 'raum_id')->orderBy('anfangsdatum')->orderBy('startzeit');
    }
}
