<?php

namespace App\Models;

use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BereichHasPersonen extends Model
{
    use HasFactory;
    public $fillable = [
        'bemerkung',
        'bereich_id',
        'projekt_has_personen_id'
    ];

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }
}
