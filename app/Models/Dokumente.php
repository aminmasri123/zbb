<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dokumente extends Model
{
    use HasFactory;

    public $fillable = [
        'name',
        'typ',
        'version',
        'dateipfad',
        'dateipfadName',
        'beschreibung',
    ];


}
