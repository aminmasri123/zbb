<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bereichsauswahl extends Model
{
    use HasFactory;
    protected $fillable = [
        'teilnehmer_id',
        'bereich_id1',
        'bereich_id2',
        'bereich_id3',
        'bereich_id4',
        'user_create',
        'user_update'
        ];

}
