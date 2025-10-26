<?php

namespace App\Models;

use App\Models\Zeitraum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GruppeHasPersonen extends Model
{
    use HasFactory;
    protected $fillable = ['personen_id', 'gruppe_id', 'startzeit', 'endzeit'];

    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }
}
