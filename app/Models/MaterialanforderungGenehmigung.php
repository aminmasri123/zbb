<?php

namespace App\Models;

use App\Models\Personen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialanforderungGenehmigung extends Model
{
    use HasFactory;
    protected $fillable = [
        'anforderung_id',
        'genehmiger_id',
        'kommentar',
        'status'
    ];

    public function anforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'anforderung_id');
    }

    public function genehmiger()
    {
        return $this->belongsTo(Personen::class, 'genehmiger_id');
    }
}
