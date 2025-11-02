<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zielgruppe extends Model
{
    use HasFactory;

    protected $fillable = [
        'bezeichnung',
        'beschreibung',
    ];
}
