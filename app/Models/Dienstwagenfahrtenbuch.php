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
        'date',
        'start_km',
        'end_km',
        'zweck',
        'ziel'
    ];
    protected $dates = [
        'date',
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }

    public function fahrer()
    {
        return $this->belongsTo(Personen::class, 'person_id')->where('typ', 'mitarbeiter');
    }
}
