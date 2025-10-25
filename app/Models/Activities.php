<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activities extends Model
{
    use HasFactory;
    protected $fillable = [
        'beschreibung',
        'model_type',
        'model_id',
        'user_id',
        'aktivitaet_typ',
        'erstellt_am'
    ];
}
