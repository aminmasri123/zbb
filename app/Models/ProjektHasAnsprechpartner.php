<?php

namespace App\Models;

use App\Models\PartnerHasPartnerschaftstypen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasAnsprechpartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'ansprechpartner_id',
    ];
    public function partnerHasPartnerschaftstyp()
    {
        return $this->belongsTo(PartnerHasPartnerschaftstypen::class, 'ansprechpartner_id', 'id');
    }
}
