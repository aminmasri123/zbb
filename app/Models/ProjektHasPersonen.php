<?php

namespace App\Models;
use App\Models\Projekt;

use App\Models\Standort;
use App\Models\Zeitraum;
use App\Models\Austritttypen;
use App\Models\ProjektHasPersonenMeta;
use App\Models\ProjektHasTeilnehmerLuv;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjektHasTeilnehmerAbschluss;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasPersonen extends Pivot //Model
{

    use HasFactory;
    protected $table = 'projekt_has_personens'; // ✅ wichtig, da Pivot keine Tabelle rät
    protected $primaryKey = 'id'; // ✅ notwendig
    public $incrementing = true;  // ✅ Pivot darf eine eigene ID haben
    protected $keyType = 'int';   // ✅ integer ID


    protected $fillable = [
        'id',
        'projekt_id',
        'personen_id',
        'standort_id',
        'ergebnisse_id',
        'status',
        'bemerkung'
    ];
    public function meta()
    {
        return $this->hasOne(ProjektHasPersonenMeta::class,  'projekt_person_id')
            ->with('betreuer', 'projektbegleiter');
    }

    public function teilnehmer()
    {
        return $this->belongsTo(Personen::class, 'personen_id')->where('typ', 'teilnehmer');
    }

    public function standort()
    {
        return $this->belongsTo(Standort::class, 'standort_id');
    }
    public function projekt()
    {
        return $this->belongsTo(Projekt::class);
    }

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }
    public function austrittttypen()
    {
        return $this->hasOne(Austritttypen::class, 'id', 'austritttypen_id');
    }

    public function abschluss()
    {
        return $this->hasOne(ProjektHasTeilnehmerAbschluss::class, 'projekt_has_person_id', 'id');
    }

    public function luv()
    {
        return $this->hasMany(ProjektHasTeilnehmerLuv::class, 'projekt_person_id');
    }

    public function intakeChecklistCompletions()
    {
        return $this->hasMany(ParticipationIntakeChecklistCompletion::class, 'project_person_id');
    }

    public function tasks()
    {
        return $this->hasMany(AppTask::class, 'project_person_id');
    }

    public function completionChecklistCompletions()
    {
        return $this->hasMany(ParticipationCompletionChecklistCompletion::class, 'project_person_id');
    }

    public function completionReports()
    {
        return $this->hasMany(ParticipationCompletionReport::class, 'project_person_id');
    }

    public function portalInvitations()
    {
        return $this->hasMany(ParticipantPortalInvitation::class, 'project_person_id');
    }

    public function portalMessages()
    {
        return $this->hasMany(ParticipantPortalMessage::class, 'project_person_id');
    }

    public function consentEvents()
    {
        return $this->hasMany(ParticipantConsentEvent::class, 'project_person_id');
    }
}
