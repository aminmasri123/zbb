<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlassenbuchTyp extends Model
{
    use HasFactory;

    protected $table = 'klassenbuch_typen';

    protected $fillable = [
        'slug',
        'name',
        'beschreibung',
        'aktiv',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
    ];
}
