<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BerufsorientierungBewertung extends Model
{
    protected $table = 'berufsorientierung_bewertungen';

    protected $fillable = [
        'gruppe_id', 'personen_id', 'user_id', 'kriterium', 'kriterium_label',
        'bewertung', 'bemerkung', 'legacy_bewertungsbogen_id',
    ];

    protected $casts = ['bewertung' => 'integer'];
}
