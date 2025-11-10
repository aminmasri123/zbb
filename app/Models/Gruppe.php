<?php

namespace App\Models;

use App\Models\Raeume;
use App\Models\Bereich;
use App\Models\Personen;
use App\Models\Zeitraum;
use App\Models\GruppeHasPersonen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gruppe extends Model
{
    use HasFactory;

    public $fillable =
    [
        'personen_id',
        'raum_id',
        'bereich_id',
        'projekt_id',
        'anfangsdatum',
        'enddatum',
        'startzeit',
        'endzeit',
    ];

    public function teilnehmer()
    {
        return $this->belongsToMany(Personen::class, 'gruppe_has_personens', 'gruppe_id', 'personen_id')
            ->using(GruppeHasPersonen::class) // dein Pivot-Modell registrieren
            ->withPivot(['user_id',
                'tage_id',
                'anwesenheitsstatuten_id',
                'bemerkung',
                'zeittatsaechlich_id',
                'zeitgeplant_id'
            ])
            ->where('personens.typ', 'teilnehmer')
            ->where('aktiv', 1);
    }

    public function betreuer()
    {
        return $this->hasOne(Personen::class, 'id', 'personen_id');
    }

    public function raum()
    {
        return $this->hasOne(Raeume::class, 'id', 'raum_id');
    }

    public function bereich()
    {
        return $this->belongsTo(Bereich::class);
    }
}
