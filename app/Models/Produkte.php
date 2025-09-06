<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produkte extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'artikelnummer',
        'link',
        'preis',
        'anzahl',
        'status',
    ];
}
