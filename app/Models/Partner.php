<?php

namespace App\Models;

use App\Models\Ansprechpartner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'typ',
        'beschreibung'
    ];


    public function ansprechpartner(): HasMany
    {
        return $this->hasMany(Ansprechpartner::class, 'partner_id', 'id');
    }
}
