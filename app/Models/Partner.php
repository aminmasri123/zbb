<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Partnerschaftstypen;
use Illuminate\Database\Eloquent\Model;
use App\Models\PartnerHasPartnerschaftstypen;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    public function ansprechpartnerDirekt()
    {
        return $this->belongsToMany(
            Personen::class,
            'partner_has_personens',
            'partner_id',
            'ansprechpartner_id'
        );
    }
}
