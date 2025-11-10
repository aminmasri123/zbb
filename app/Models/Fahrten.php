<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Fahrtarten;
use App\Models\Abrechnungen;
use App\Models\Transportarten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fahrten extends Model
{
    use HasFactory;

        protected $fillable = [
        'person_id',
        'personal_id',
        'fahrtart_id',
        'datum',
        'start',
        'ziel',
        'entfernung_km',
        'kosten_berechnet',
        'status',
    ];



    public function person()
    {
        return $this->belongsTo(Personen::class);
    }

    public function personal()
    {
        return $this->belongsTo(Personen::class, 'personal_id');
    }

    public function fahrtarten()
    {
        return $this->belongsTo(Fahrtarten::class, 'fahrtart_id');
    }

    public function abrechnungen()
    {
        return $this->belongsToMany(Abrechnungen::class, 'abrechnung_has_fahrtens');
    }
}
