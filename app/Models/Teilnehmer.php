<?php

namespace App\Models;

use App\Models\Adresse;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teilnehmer extends Model
{
    use HasFactory;


    protected $fillable = [
        'id',
        'vorname',
        'nachname',
        'geschlecht',
        'current_team_id',

    ];
    public function adresse()
    {
        return $this->hasOne(Adresse::class);
    }

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_teilnehmers', 'teilnehmer_id', 'projekt_id');
    }

}
