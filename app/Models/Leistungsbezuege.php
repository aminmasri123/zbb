<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leistungsbezuege extends Model
{
    use HasFactory;

    protected $fillable = ['bezeichnung', 'beschreibung'];

    public function sozialeDaten()
    {
        return $this->hasO(SozialeDaten::class);
    }
}
