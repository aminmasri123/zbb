<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EinteilungBereichKapazitaet extends Model
{
    use HasFactory;

    protected $table = 'einteilung_bereich_kapazitaeten';

    protected $fillable = [
        'einteilung_setting_id',
        'bereich_id',
        'plaetze',
    ];

    protected $casts = [
        'plaetze' => 'integer',
    ];

    public function setting()
    {
        return $this->belongsTo(EinteilungSetting::class, 'einteilung_setting_id');
    }

    public function bereich()
    {
        return $this->belongsTo(Bereich::class, 'bereich_id');
    }
}
