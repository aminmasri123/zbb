<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenHasNotizen extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'projekt_person_id',
        'user_id',
        'notiztyp_id',
        'prioritaet_id',
        'kategorie_id',
        'titel',
        'notizinhalt',
    ];

    public function notizkategorie()
    {
        return $this->hasOne(Notizvarianten::class, 'id', 'kategorie_id')->where('typ', 'kategorie');
    }

    public function notiztyp()
    {
        return $this->hasOne(Notizvarianten::class, 'id', 'notiztyp_id')->where('typ', 'typ');
    }

    public function notizprioritaet()
    {
        return $this->hasOne(Notizvarianten::class, 'id', 'prioritaet_id')->where('typ', 'prioritaet');
    }

    public function user()
    {
        return $this->belongsTo(Personen::class, 'user_id', 'id')
            ->select('id', 'vorname', 'nachname');
    }

    public function projektTeilnahme()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_person_id');
    }
}
