<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeraetHasAusgabe extends Model
{
    use HasFactory;
    protected $fillable = [
        'geraet_id',
        'ausgabe_id',
    ];
}
