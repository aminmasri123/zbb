<?php

namespace App\Models;

use App\Models\Bereichsauswahl;
use App\Models\EinteilungBereiche;
use App\Models\Partner;
use App\Models\Personen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenIstSchueler extends Model
{
    use HasFactory;

    protected $casts = [
        'eee' => 'boolean',
        'foerderschueler' => 'boolean',
        'foederschueler' => 'boolean',
    ];

     protected $fillable = [
        'id',
        'person_id',
        'klasse',
        'foerderschueler',
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
            $query->forSchuljahr($schuljahr);
        }

        if ($teil) {
            $query->where('teil', $teil);
        }
        $query->with('person');
        return $query;
    }

    public function scopeForSchuljahr($query, $schuljahr)
    {
        $value = trim((string) $schuljahr);
        preg_match('/\d{4}/', $value, $matches);
        $startYear = $matches[0] ?? trim(explode('/', $value, 2)[0]);

        return $query->where(function ($yearQuery) use ($value, $startYear) {
            $yearQuery->where('schuljahr', $value);

            if ($startYear !== '') {
                $yearQuery
                    ->orWhere('schuljahr', $startYear)
                    ->orWhere('schuljahr', 'like', $startYear . '/%')
                    ->orWhere('schuljahr', 'like', $startYear . '-%');
            }
        });
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

    public function schule()
    {
        return $this->belongsTo(Partner::class, 'schule_id');
    }

    public function bereichsauswahl()
    {
        return $this->hasOne(Bereichsauswahl::class, 'teilnehmer_id', 'id');
    }

    public function einteilungen()
    {
        return $this->morphMany(EinteilungBereiche::class, 'teilnehmende');
    }

}
