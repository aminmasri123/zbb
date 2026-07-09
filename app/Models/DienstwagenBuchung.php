<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DienstwagenBuchung extends Model
{
    use HasFactory;

    protected $table = 'dienstwagen_buchungen';

    protected $fillable = [
        'dienstwagen_id',
        'person_id',
        'user_id',
        'start_at',
        'end_at',
        'ziel',
        'zweck',
        'status',
        'start_km',
        'end_km',
        'notizen',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function dienstwagen()
    {
        return $this->belongsTo(Dienstwagen::class);
    }

    public function person()
    {
        return $this->belongsTo(Personen::class, 'person_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
