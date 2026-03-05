<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasAnprechpartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'ansprechpartner_id',
    ];
}
