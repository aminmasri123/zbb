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
        'projekt_has_personen_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }

    public function bereich()
    {
        return $this->belongsTo(Bereich::class, 'bereich_id');
    }

    public function projektHasPersonen()
    {
        return $this->belongsTo(ProjektHasPersonen::class, 'projekt_has_personen_id');
    }
}
