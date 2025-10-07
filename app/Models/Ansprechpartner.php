<?php

namespace App\Models;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ansprechpartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'vorname',
        'nachname',
        'geschlecht',
        'typ',
        'partner_id',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }
}
