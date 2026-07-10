<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseBeurteilung extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_beurteilungen';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'kriterium_id',
        'user_id',
        'bewertung',
        'bemerkung',
    ];

    protected $casts = [
        'bewertung' => 'integer',
    ];

    public function kriterium()
    {
        return $this->belongsTo(PotenzialanalyseKriterium::class, 'kriterium_id');
    }
}
