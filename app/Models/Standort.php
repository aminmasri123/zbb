<?php

namespace App\Models;

use App\Models\Projekt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Standort extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'beschreibung'];


    public function teilnehmer()
    {
        return $this->belongsToMany(Teilnehmer::class, 'standort_has_teilnehmers', 'standort_id', 'teilnehmer_id');
    }

    public function user()
    {
        return $this->belongsToMany(User::class, 'standort_has_users', 'standort_id', 'user_id');
    }


}
