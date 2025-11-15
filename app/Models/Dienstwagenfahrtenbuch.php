<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Dienstwagen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dienstwagenfahrtenbuch extends Model
{
    use HasFactory;

    protected $fillable = [
        'dienstwagen_id',
        'person_id',
        'datum',
        'start_km',
        'ende_km',
        'zweck',
        'ziel'
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }

    public function fahrer()
    {
        return $this->belongsTo(Personen::class)->where('typ', 'Mitarbeiter');
    }
}
