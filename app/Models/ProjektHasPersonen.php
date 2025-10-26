<?php

namespace App\Models;
use App\Models\zeitraum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasPersonen extends Pivot //Model
{

    use HasFactory;
    protected $table = 'projekt_has_personens'; // ✅ wichtig, da Pivot keine Tabelle rät


    protected $fillable = [
        'id',
        'projekt_id',
        'personen_id',
        'status',
        'bemerkung'
    ];



    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }
}
