<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasPersonen extends Model //Pivot //Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'projekt_id',
        'personen_id',
        'status',
        'bemerkung'
    ];
}
