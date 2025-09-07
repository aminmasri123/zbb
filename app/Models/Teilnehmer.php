<?php

namespace App\Models;

use App\Models\Adresse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Teilnehmer extends Model
{
    use HasFactory;


    protected $fillable = [
        'id',
        'vorname',
        'nachname',
        'geschlecht',

    ];
    public function adresse()
    {
        return $this->hasOne(Adresse::class);
    }
}
