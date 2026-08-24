<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseProfilKompetenz extends Model
{
    protected $table = 'potenzialanalyse_profil_kompetenzen';

    protected $fillable = [
        'profil_id',
        'key',
        'label',
        'kategorie',
        'kategorie_label',
        'kategorie_code',
        'beschreibung',
        'selbsteinschaetzung_text',
        'bewertungsbeschreibungen',
        'sort_order',
        'aktiv',
    ];

    protected $casts = [
        'bewertungsbeschreibungen' => 'array',
        'sort_order' => 'integer',
        'aktiv' => 'boolean',
    ];

    public function profil()
    {
        return $this->belongsTo(PotenzialanalyseProfil::class, 'profil_id');
    }
}
