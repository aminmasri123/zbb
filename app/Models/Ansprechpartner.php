<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ansprechpartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'vorname',
        'nachname',
        'geschlecht',
        'typ',
        'partner_id',
    ];
}
