<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialanforderungVergabevermerk extends Model
{
    use HasFactory;
    protected $fillable = [
        'anforderung_id',
        'lieferung_art',
        'begruendung',
        'lieferant',
        'lieferung_option',
    ];

    public function anforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'anforderung_id');
    }
}
