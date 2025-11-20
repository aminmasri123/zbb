<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Ergebnisse;
use App\Models\Zielgruppe;
use App\Models\Abschluesse;
use App\Models\Austritttypen;
use App\Models\ProjektHasPersonen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasPersonenMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_person_id',
        'betreuer_id',
        'austritt_id',
        'zielgruppe_id',
        'projektabschluss_id',
    ];


    /**
     * 1️⃣ Beziehung zur Projekt-Person-Pivot
     * (projekt_has_personens)
     */
    public function projektPerson()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id');
    }

    /**
     * 2️⃣ Betreuer (gehört zu personens)
     */
    public function betreuer()
    {
        return $this->belongsTo(Personen::class, 'betreuer_id');
    }

    /**
     * 3️⃣ Austtrittstyp
     */
    public function verbleib()
    {
        return $this->belongsTo(Verbleibteilnehmer::class, 'verbleib_id');
    }
    public function austritt()
    {
        return $this->belongsTo(Austritttypen::class, 'austritt_id');
    }

    public function projektabschluss()
    {
        return $this->belongsTo(Ergebnisse::class, 'projektabschluss_id');
    }

    /**
     * 4️⃣ Zielgruppe
     */
    public function zielgruppe()
    {
        return $this->belongsTo(Zielgruppe::class, 'zielgruppe_id');
    }
}
