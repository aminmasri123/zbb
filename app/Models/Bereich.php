<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bereich extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'aktiv',
        'code',
        'beschreibung',
        'unterweisung_themen',
    ];

    protected $casts = [
        'unterweisung_themen' => 'array',
    ];

    public function projekte()
    {
        return $this->belongsToMany(Projekt::class, 'projekt_has_bereiches', 'bereich_id', 'projekt_id');
    }

    public function bereichHasPersonen()
    {
        return $this->hasMany(BereichHasPersonen::class, 'bereich_id', 'id');
    }

    public function dokumente()
    {
        return $this->belongsToMany(Dokumente::class, 'dokument_has_bereiches', 'bereich_id', 'dokument_id');
    }
}
