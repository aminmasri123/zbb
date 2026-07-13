<?php

namespace App\Models;

use App\Models\Abschluesse;
use App\Models\Adresse;
use App\Models\Baenke;
use App\Models\Dienstwagen;
use App\Models\Dienstwagenfahrtenbuch;
use App\Models\EinteilungBereiche;
use App\Models\Fahrten;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partnerschaftstypen;
use App\Models\PersonenHasAbschluesse;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\PersonenHasNotizen;
use App\Models\PersonenHasSozialedaten;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use App\Models\Zielgruppe;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Personen extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'vorname',
        'nachname',
        'geburtsdatum',
        'geschlecht',
        'aktiv',
        'typ'
    ];
    protected $date = [
        'geburtsdatum',
    ];
    protected $casts = [
        'geburtsdatum' => 'date',
    ];


    public function scopeMitarbeiter($query)
    {
        return $query->where('typ', 'mitarbeiter');
    }

    public function scopeTeilnehmer($query)
    {
        return $query->where('typ', 'teilnehmer');
    }
    public function scopeArbeitsvermittler($query)
    {
        return $query->where('typ', 'ansprechpartner')
            ->whereHas('partnerTyp', function ($q) {
                $q->where('bezeichnung', 'Arbeitsvermittler');
            })
            ->with('partnerTyp');
    }



    public function scopeVisibleForUser($query, User $user)
    {
        $scope = RoleDataAccessSetting::scopeForUser($user, 'participant');

        if ($scope === 'all') {
            return $query;
        }

        if ($scope === 'department') {
            $departmentIds = $user->projekte()->pluck('projekts.abteilung_id')->filter()->unique()->values();

            if ($departmentIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('projekte', function ($q) use ($departmentIds) {
                $q->whereIn('projekts.abteilung_id', $departmentIds);
            });
        }

        if ($scope === 'own_projects') {
            $projektIds = $user->projekte()->pluck('projekts.id')->filter()->unique()->values();

            if ($projektIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('projekte', function ($q) use ($projektIds) {
                $q->whereIn('projekts.id', $projektIds);
            });
        }

        if ($scope === 'own_locations') {
            $standortIds = $user->standorte()->pluck('standorts.id')->filter()->unique()->values();

            if ($standortIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('projekte', function ($q) use ($standortIds) {
                $q->whereIn('projekt_has_personens.standort_id', $standortIds);
            });
        }

        if ($scope === 'current_project_same_location') {
            $standortIds = $user->standorte()->pluck('standorts.id')->filter()->unique()->values();

            if (! $user->current_team_id || $standortIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('projekte', function ($q) use ($user, $standortIds) {
                $q->where('projekt_has_personens.projekt_id', $user->current_team_id)
                    ->whereIn('projekt_has_personens.standort_id', $standortIds);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', 1);
    }

    public function partnerTyp()
    {
        return $this->belongsToMany(Partnerschaftstypen::class, 'partner_has_partnerschaftstypens', 'ansprechpartner_id', 'partnerschaftstypen_id');
    }


        public function schueler()
        {
            return $this->hasMany(PersonenIstSchueler::class, 'person_id', 'id');
        }

    public function gruppen()
    {
        return $this->belongsToMany(Gruppe::class, 'gruppe_has_personens', 'personen_id', 'gruppe_id')
            ->using(GruppeHasPersonen::class) // dein Pivot-Modell registrieren
            ->withPivot(['personen_id', 'gruppe_id', 'user_id','tage_id','anwesenheitsstatuten_id', 'bemerkung', 'zeittatsaechlich_id', 'zeitgeplant_id', 'id'])
            ->with('bereich');
    }

    public function anwesenheiten() {
        return $this->hasMany(GruppeHasPersonen::class, 'personen_id', 'id')
            ->with(['gruppe.bereich', 'zeitgeplant', 'zeittatsaechlich', 'status']);
    }
     public function praktika() {
        return $this->hasMany(PersonenHasBildungsmassnahmen::class, 'person_id', 'id');
    }

    public function notizen(){
        return $this->hasMany(PersonenHasNotizen::class, 'person_id', 'id');
    }

    public function fahrtabrechnungen(){
        return $this->hasMany(Fahrten::class, 'person_id', 'id');
    }

    public function standorte(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_has_personens', 'personen_id', 'standort_id');
    }

    public function adresses()
    {
        return $this->morphMany(Adresse::class, 'model');
    }

    public function baenke()
    {
        return $this->morphMany(Baenke::class, 'model');
    }

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_personens', 'personen_id', 'projekt_id')
            ->using(ProjektHasPersonen::class)
            ->withPivot('id', 'bemerkung', 'status', 'standort_id')
            ->as('pivotModel');
    }

    public function portalProfile()
    {
        return $this->hasOne(ParticipantPortalProfile::class, 'person_id');
    }

    public function kontaktes()
    {
        return $this->morphMany(Kontakte::class, 'model');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'person_id');
    }

    public function abschluesse(): BelongsToMany
    {
        return $this->belongsToMany(Abschluesse::class, 'personen_has_abschluesses', 'person_id', 'abschluss_id')
        ->using(PersonenHasAbschluesse::class) // <== Pivot Model verwenden
            ->withPivot('bezeichnung', 'start', 'end', 'id')                    // <- damit du das Pivot Model findest
            ->as('pivotModel');
    }

    public function sozialedaten(){
        return $this->hasOne(PersonenHasSozialedaten::class, 'person_id', 'id');
    }
    public function zielgruppen(): BelongsToMany
    {
        return $this->belongsToMany(Zielgruppe::class, 'personen_has_zielgruppes', 'person_id', 'zielgruppe_id');
    }

    public function dienstwagen()
    {
        return $this->belongsToMany(Dienstwagen::class, 'dienstwagen_has_personens', 'person_id', 'dienstwagen_id');
    }

    public function dienstwagenfahrten()
    {
        return $this->hasMany(Dienstwagenfahrtenbuch::class, 'person_id', 'id');
    }

    public function projektStandorte()
    {
        return $this->belongsToMany(Standort::class, 'projekt_has_personens', 'personen_id', 'standort_id')
            ->withPivot('projekt_id');
    }

    public function einteilungen()
    {
        return $this->morphMany(EinteilungBereiche::class, 'teilnehmende')->where('typ', 'teilnehmer');
    }





    // ⚡ Booted Event für automatisches Löschen
    protected static function booted()
    {
        static::deleting(function ($person) {
            // Adresse löschen
            $person->adresses()->delete();

            // Kontakte löschen
            $person->kontaktes()->delete();
        });
    }
}
