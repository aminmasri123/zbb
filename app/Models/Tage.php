<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tage extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'datum',
        'wochentag',
        'feiertag_typ',
        'feiertag_name'
    ];
}
