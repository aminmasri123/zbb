<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOffer extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected $casts = ['positionen' => 'array', 'empfohlen' => 'boolean', 'revision' => 'integer',
        'angebotsdatum' => 'date:Y-m-d', 'gueltig_bis' => 'date:Y-m-d', 'netto' => 'decimal:2', 'brutto' => 'decimal:2'];
    public function anforderung() { return $this->belongsTo(Materialanforderung::class, 'anforderung_id'); }
}
