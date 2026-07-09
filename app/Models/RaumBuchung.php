<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaumBuchung extends Model
{
    use HasFactory;

    protected $table = 'raum_buchungen';

    protected $fillable = [
        'raum_id',
        'projekt_id',
        'gruppe_id',
        'gebucht_von_user_id',
        'gebucht_von_personen_id',
        'titel',
        'typ',
        'start_at',
        'end_at',
        'teilnehmerzahl',
        'status',
        'zweck',
        'bemerkung',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'teilnehmerzahl' => 'integer',
    ];

    public function raum()
    {
        return $this->belongsTo(Raeume::class, 'raum_id');
    }

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function gruppe()
    {
        return $this->belongsTo(Gruppe::class, 'gruppe_id');
    }

    public function gebuchtVon()
    {
        return $this->belongsTo(User::class, 'gebucht_von_user_id');
    }

    public function gebuchtVonPerson()
    {
        return $this->belongsTo(Personen::class, 'gebucht_von_personen_id');
    }
}
