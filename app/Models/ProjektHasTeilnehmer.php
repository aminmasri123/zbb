<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasTeilnehmer extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'teilnehmer_id',
    ];
}
