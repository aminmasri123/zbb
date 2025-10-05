<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zeitraum extends Model
{
    use HasFactory;
    protected $fillable = [
        'von',
        'bis',
        'typ',
        'model_type',
        'model_id'
    ];
    protected $dates = [
        'von',
        'bis'
    ];
}
