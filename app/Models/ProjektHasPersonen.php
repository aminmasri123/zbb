<?php

namespace App\Models;
use App\Models\Standort;

use App\Models\zeitraum;
use App\Models\Austritttypen;
use App\Models\ProjektHasPersonenMeta;
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
        return $this->hasOne(ProjektHasPersonenMeta::class,  'projekt_person_id');
    }

    public function standort()
    {
        return $this->hasOne(Standort::class, 'standort_id');
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
}
