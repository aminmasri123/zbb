<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EinteilungBereiche extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'person_type',
        'bereich_id',
        'runde',
    ];



    // 🔹 Bereich
    public function bereich()
    {
        return $this->belongsTo(Bereich::class);
    }

    // 🔹 Polymorphe Beziehung zur Person
    public function teilnehmende()
    {
        return $this->morphTo();
    }
}
