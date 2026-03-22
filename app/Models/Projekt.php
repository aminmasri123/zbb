<?php

namespace App\Models;
use App\Models\Abteilung;
use App\Models\Bereich;
use App\Models\Dokumente;
use App\Models\Kostenstelle;
use App\Models\Partner;
use App\Models\PartnerHasPartnerschaftstypen;
use App\Models\Personen;
use App\Models\ProjektHasAnsprechpartner;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Projekt extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'abteilung_id',
        'beschreibung',
        'kostenstelle',
        'aktiv',
    ];


    public function scopeAktiv($query)
    {
        return $query->where('aktiv', 1);
    }


    public function abteilung()
    {
        return $this->belongsTo(Abteilung::class, 'abteilung_id', 'id');
    }
    public function projektHasAnsprechpartner()
    {
        return $this->hasMany(ProjektHasAnsprechpartner::class, 'projekt_id', 'id');
    }

    /* public function ansprechpartner()
    {
        return $this->hasManyThrough(
            Personen::class, // Ziel: Personen
            PartnerHasPartnerschaftstypen::class, // Pivot/Intermediate
            'projekt_id', // FK in Pivot auf Projekt
            'id', // PK in Person
            'id', // PK in Projekt
            'ansprechpartner_id', // FK in Pivot auf Person

        );
    }  */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            PartnerHasPartnerschaftstypen::class,   // erste Zwischentabelle
            'projekt_has_ansprechpartners',          // Pivot-Tabelle Projekt ↔ Ansprechpartner
            'projekt_id',                            // FK auf Projekt
            'ansprechpartner_id'                     // FK auf PartnerHasPartnerschaftstypen
        )
        ->join('partners', 'partner_has_partnerschaftstypens.partner_id', '=', 'partners.id')
        ->select('partners.*');                     // gibt nur Partner zurück
    }



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
