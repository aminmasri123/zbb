<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PotenzialanalyseSelbsteinschaetzung extends Model
{
    use HasFactory;

    protected $table = 'potenzialanalyse_selbsteinschaetzungen';

    protected $fillable = [
        'gruppe_id',
        'personen_id',
        'kriterium_id',
        'user_id',
        'bewertung',
        'bemerkung',
        'submitted_at',
    ];

    protected $casts = [
        'bewertung' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function kriterium()
    {
        return $this->belongsTo(PotenzialanalyseKriterium::class, 'kriterium_id');
    }
}
