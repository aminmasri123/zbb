<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasKostenstelle extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'kostenstelle_id',
        'gueltig_von',
        'gueltig_bis',
    ];
}
