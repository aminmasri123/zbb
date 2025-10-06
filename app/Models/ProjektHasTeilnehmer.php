<?php

namespace App\Models;

use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjektHasTeilnehmer extends Pivot //Model //Pivot
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'projekt_id',
        'teilnehmer_id',
    ];



    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }

}
