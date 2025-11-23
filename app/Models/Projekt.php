<?php

namespace App\Models;
use App\Models\User;
use App\Models\Gruppe;
use App\Models\Raeume;
use App\Models\Bereich;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\zeitraum;
use App\Models\Abteilung;
use App\Models\Dokumente;
use App\Models\Teilnehmer;
use App\Models\Kostenstelle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Projekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'abteilung_id',
        'beschreibung',
        'kostenstelle',
        'aktiv'
    ];

    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id', 'id');
    }

 /*    public function personenStandorte()
    {
        return $this->belongsToMany(Standort::class, 'projekt_has_personens', 'projekt_id', 'standort_id')
            ->withPivot('personen_id');
    }
 */

    public function kostenstellen()
    {
        return $this->belongsToMany(Kostenstelle::class, 'projekt_has_kostenstelles', 'projekt_id', 'kostenstelle_id');
    }
    public function teilnehmer()
    {
        return $this->belongsToMany(Personen::class, 'projekt_has_personens', 'projekt_id', 'personen_id');
    }


    public function mitarbeiter()
    {
        return $this->belongsToMany(Personen::class, 'projekt_has_personens', 'projekt_id', 'personen_id')
            ->where('personens.typ', 'mitarbeiter')
            ->withPivot(['standort_id', 'status']);
    }

    public function standorte(){
        return $this->belongsToMany(Standort::class, 'projekt_has_personens', 'projekt_id', 'standort_id')
            ->withPivot(['personen_id']);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_has_projekts', 'projekt_id', 'user_id');
    }

    public function bereiche()
    {
        return $this->belongsToMany(Bereich::class, 'projekt_has_bereiches', 'projekt_id', 'bereich_id');
    }
    public function raeume()
    {
        return $this->belongsToMany(Raeume::class, 'projekt_has_raeumes', 'projekt_id', 'raum_id');
    }
    public function dokumente()
    {
        return $this->belongsToMany(Dokumente::class, 'projekt_has_dokumentes', 'projekt_id', 'dokument_id');
    }

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model')->orderBy('antragsdatum', 'desc');
    }



}
