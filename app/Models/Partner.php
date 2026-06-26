<?php

namespace App\Models;

use App\Models\PartnerHasPartnerschaftstypen;
use App\Models\Partnerschaftstypen;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\ProjektHasAnprechpartner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'beschreibung'
    ];



    public function partnerschaftstypens()
    {
        return $this->belongsToMany(
            Partnerschaftstypen::class,
            'partner_has_partnerschaftstypens',
            'partner_id',
            'partnerschaftstypen_id'
        )->withPivot('ansprechpartner_id', 'rolle');
    }


    /**
     * Pivot Zuordnungen (Typ + Ansprechpartner + Rolle)
     */
    public function partnerschaftstypenZuordnung()
    {
        return $this->hasMany(
            PartnerHasPartnerschaftstypen::class,
            'partner_id',
            'id'
        );
    }

    /**
     * Nur falls du später direkte Ansprechpartner brauchst
     */
    public function ansprechpartners()
    {
        return $this->belongsToMany(
            Personen::class,
            'partner_has_partnerschaftstypens',
            'partner_id',
            'ansprechpartner_id'
        )->withPivot('partnerschaftstypen_id', 'rolle');
    }

    public function projektHasAnsprechpartner()
    {
        return $this->hasMany( ProjektHasAnprechpartner::class, 'ansprechpartner_id', 'id');
    }

    public function schueler()
    {
        return $this->hasMany( PersonenIstSchueler::class, 'schule_id', 'id');
    }





}
