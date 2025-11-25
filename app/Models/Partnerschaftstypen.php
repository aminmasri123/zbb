<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PartnerHasPartnerschaftstypen;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partnerschaftstypen extends Model
{
    use HasFactory;
    protected $fillable = [
        'bezeichnung',
        'beschreibung'
    ];

    public function partner()
{
    return $this->hasMany(PartnerHasPartnerschaftstypen::class, 'partnerschaftstypen_id');
}



}
