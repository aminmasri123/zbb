<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlassenbuchEintrag extends Model
{
    use HasFactory;

    protected $table = 'klassenbuch_eintraege';

    protected $fillable = [
        'klassenbuch_woche_id',
        'created_by_user_id',
        'updated_by_user_id',
        'datum',
        'stunde',
        'fach',
        'thema',
        'azubi_nummern',
        'signum',
        'bemerkung',
    ];

    protected $casts = [
        'datum' => 'date',
    ];

    public function woche()
    {
        return $this->belongsTo(KlassenbuchWoche::class, 'klassenbuch_woche_id');
    }
}
