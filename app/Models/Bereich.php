<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bereich extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'beschreibung',
    ];
}
