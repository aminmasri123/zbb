<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseBericht extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_berichte';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'user_id',
        'status',
        'staerken',
        'entwicklungsfelder',
        'empfehlung',
        'bericht_text',
        'fertiggestellt_at',
    ];

    protected $casts = [
        'fertiggestellt_at' => 'datetime',
    ];
}
