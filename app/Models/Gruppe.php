<?php

namespace App\Models;

use App\Models\Bereich;
use App\Models\Personen;
use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gruppe extends Model
{
    use HasFactory;

    public $fillable =
    [
        'personen_id',
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
        ->where('personens.typ', 'teilnehmer');
    }

    public function betreuer()
    {
        return $this->hasOne(Personen::class, 'id', 'personen_id');
    }

    public function bereich()
    {
        return $this->belongsTo(Bereich::class);
    }
}
