<?php

namespace App\Models;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\Partnerschaftstypen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartnerHasPartnerschaftstypen extends Model
{
    use HasFactory;
    protected $table = 'partner_has_partnerschaftstypens';

    protected $fillable = [
        'partner_id',
        'partnerschaftstypen_id',
        'ansprechpartner_id',
        'rolle'
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function typ()
    {
        return $this->belongsTo(Partnerschaftstypen::class, 'partnerschaftstypen_id');
    }

    public function ansprechpartner()
    {
        return $this->belongsTo(Personen::class, 'ansprechpartner_id');
    }
}
