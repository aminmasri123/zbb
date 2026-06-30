<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EinteilungSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'partner_id',
        'schuljahr',
        'teil',
        'runden_anzahl',
        'standard_kapazitaet',
        'user_create',
        'user_update',
    ];

    protected $casts = [
        'runden_anzahl' => 'integer',
        'standard_kapazitaet' => 'integer',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function kapazitaeten()
    {
        return $this->hasMany(EinteilungBereichKapazitaet::class, 'einteilung_setting_id');
    }
}
