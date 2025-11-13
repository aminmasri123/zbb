<?php

namespace App\Models;

use App\Models\Raeume;
use App\Models\Adresse;
use App\Models\Projekt;
use App\Models\Personen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Standort extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'beschreibung'];


    public function personen()
    {
        return $this->belongsToMany(Personen::class, 'standort_has_personens', 'standort_id', 'personen_id');
    }

    public function teilnehmer()
    {
        return $this->belongsToMany(Personen::class, 'standort_has_personens', 'standort_id', 'personen_id')
            ->where('typ', 'teilnehmer');
    }

    public function user()
    {
        return $this->belongsToMany(User::class, 'standort_has_users', 'standort_id', 'user_id');
    }

    public function raeume(){
        return $this->hasMany(Raeume::class);
    }

     public function adresse()
    {
        return $this->morphMany(Adresse::class, 'model');
    }




}
