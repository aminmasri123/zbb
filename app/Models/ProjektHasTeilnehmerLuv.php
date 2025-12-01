<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasTeilnehmerLuv extends Model
{
    use HasFactory;
    protected $fillable = [
        'typ',
        'projekt_person_id',
        'von',
        'bis',
        'ausgangssituation',
        'zielvereinbarung',
    ];

   protected $casts = [
        'von' => 'date',
        'bis' => 'date',
    ];


    public function projektHasTeilnehmer(){
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id' );
    }


}
