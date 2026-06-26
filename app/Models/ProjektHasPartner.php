<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjektHasPartner extends Model
{
    use HasFactory;

    protected $table = 'projekt_has_partners';

    protected $fillable = [
        'projekt_id',
        'partner_id',
    ];

    public function projekt()
    {
        return $this->belongsTo(Projekt::class, 'projekt_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
