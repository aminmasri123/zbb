<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasBereiche extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'projekt_id',
        'bereich_id',
    ];
}
