<?php

namespace App\Models;

use App\Models\Standort;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Raeume extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'standort_id',
        'beschreibung',
        'id',
        'kapazitaet',
        'typ',
    ];


    public function standort()
    {
        return $this->belongsTo(Standort::class);
    }
}
