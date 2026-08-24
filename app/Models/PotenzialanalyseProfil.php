<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseProfil extends Model
{
    protected $table = 'potenzialanalyse_profile';

    protected $fillable = [
        'projekt_id',
        'key',
        'name',
        'version',
        'status',
        'aktiv',
        'bericht_config',
        'veroeffentlicht_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'aktiv' => 'boolean',
        'bericht_config' => 'array',
        'veroeffentlicht_at' => 'datetime',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function kompetenzen()
    {
        return $this->hasMany(PotenzialanalyseProfilKompetenz::class, 'profil_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function uebungen()
    {
        return $this->hasMany(PotenzialanalyseUebung::class, 'profil_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
