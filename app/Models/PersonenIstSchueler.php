<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonenIstSchueler extends Model
{
    use HasFactory;

     protected $fillable = [
        'id',
        'person_id',
        'klasse',
        'foederschueler',
        'eee',
        'schuljahr',
        'teil',
        'schule_id',
    ];


}
