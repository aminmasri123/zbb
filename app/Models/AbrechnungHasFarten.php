<?php

namespace App\Models;

use App\Models\Fahrten;
use App\Models\Abrechnungen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AbrechnungHasFarten extends Model
{
    use HasFactory;

    protected $fillable = [
        'abrechnung_id',
        'fahrt_id',
    ];

    public function abrechnung()
    {
        return $this->belongsTo(Abrechnungen::class);
    }

    public function fahrt()
    {
        return $this->belongsTo(Fahrten::class);
    }
}
