<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BereichsauswahlSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'projekt_id',
        'partner_id',
        'schuljahr',
        'teil',
        'auswahl_anzahl',
        'public_token',
        'zugang_aktiv',
        'user_create',
        'user_update',
    ];

    protected $casts = [
        'zugang_aktiv' => 'boolean',
        'auswahl_anzahl' => 'integer',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id', 'id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }
}
