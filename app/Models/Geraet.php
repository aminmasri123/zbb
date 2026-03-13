<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geraet extends Model
{
    use HasFactory;
    protected $table = 'geraets';
    protected $fillable = [
        'sn',
        'productID',
        'zustand',
        'geraet',
        'imLager',
        'hersteller',
        'modell',
        'baujahr',
        'garantiefrist',
        'verfuegbarkeit',
    ];
    protected $dates = [
        'baujahr',
        'garantiefrist',
    ];

    public function ausgaben()
    {
        return $this->belongsToMany(Ausgabe::class, 'geraet_ausgabes', 'ausgabe_id', 'geraet_id');
    }
    public function rueckgaben()
    {
        return $this->belongsToMany(Rueckgabe::class, 'geraet_rueckgabes', 'rueckgabe_id', 'geraet_id');
    }



}
