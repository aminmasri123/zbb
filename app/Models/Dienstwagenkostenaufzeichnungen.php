<?php

namespace App\Models;

use App\Models\Dienstwagen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dienstwagenkostenaufzeichnungen extends Model
{
    use HasFactory;

    protected $fillable = [
        'dienstwagen_id',
        'art',
        'datum',
        'betrag',
        'beschreibung'
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }
}
