<?php

namespace App\Models;

use App\Models\Projekt;
use App\Models\BereichHasPersonen;
use App\Models\BereichHasTeilnehmer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bereich extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'aktiv',
        'beschreibung',
    ];

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_bereiches', 'bereich_id', 'projekt_id');
    }

    public function bereichHasPersonen()
    {
       return $this->hasMany(BereichHasPersonen::class, 'bereich_id', 'id');
    }
}
