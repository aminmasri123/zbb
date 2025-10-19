<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandortHasPersonen extends Model
{
    use HasFactory;

    protected $table = 'standort_has_personens';

    protected $fillable = [
        'standort_id',
        'personen_id',
    ];
}
