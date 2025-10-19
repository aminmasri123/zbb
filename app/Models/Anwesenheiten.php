<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anwesenheiten extends Model
{
    use HasFactory;


     public function zeitraum()
    {
        return $this->belongsTo(Zeitraum::class);
    }

    public function teilnehmer()
    {
        return $this->belongsTo(Teilnehmer::class);
    }
}
