<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeraetHasRueckgabe extends Model
{
    use HasFactory;
    protected $table = 'geraet_has_rueckgabes'; // Name der existierenden Tabelle

    protected $fillable = [
        'geraet_id',
        'rueckgabe_id',
    ];
}
