<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EinteilungRundentermin extends Model
{
    use HasFactory;

    protected $table = 'einteilung_rundentermine';

    protected $fillable = [
        'einteilung_setting_id',
        'runde',
        'anfangsdatum',
        'enddatum',
        'startzeit',
        'endzeit',
    ];

    protected $casts = [
        'runde' => 'integer',
        'anfangsdatum' => 'date',
        'enddatum' => 'date',
    ];

    public function setting()
    {
        return $this->belongsTo(EinteilungSetting::class, 'einteilung_setting_id');
    }
}
