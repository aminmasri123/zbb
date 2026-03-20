<?php

namespace App\Models;

use App\Models\Personen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenIstSchueler extends Model
{
    use HasFactory;

     protected $fillable = [
        'id',
        'person_id',
        'klasse',
        'foederschueler',
        'eee',
        'schuljahr',
        'teil',
        'schule_id',
    ];


    public function scopeFilterSchueler($query, $schuleId = null, $schuljahr = null, $teil = null)
    {
        if ($schuleId) {
            $query->where('schule_id', $schuleId);
        }

        if ($schuljahr) {
            $query->where('schuljahr', $schuljahr);
        }

        if ($teil) {
            $query->where('teil', $teil);
        }

        return $query;
    }

    public function scopeSchulform($query, $alle_teilnehmer)
    {
        $anzahlSchueler = $alle_teilnehmer->count();
        $anzahlFoerderschueler = $alle_teilnehmer->where('foerderschueler', true)->count();
        if($anzahlFoerderschueler/$anzahlSchueler > 0.5){
            $query = 'Förderschule';
        }else{
            $query = 'Gemeinschaftsschule';
        }
        return $query;
    }

    public function person()
    {
        return $this->belongsTo(Personen::class);
    }

}
