<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialanforderungKommentar extends Model
{
    protected $table = 'materialanforderung_kommentare';

    protected $fillable = [
        'anforderung_id', 'artikel_id', 'parent_id', 'user_id', 'grund', 'body',
        'vorgeschlagener_preis', 'vorgeschlagener_link', 'antwort_erforderlich',
        'geklaert_am', 'geklaert_von_id',
    ];

    protected $casts = [
        'antwort_erforderlich' => 'boolean',
        'vorgeschlagener_preis' => 'decimal:2',
        'geklaert_am' => 'datetime',
    ];

    public function anforderung()
    {
        return $this->belongsTo(Materialanforderung::class, 'anforderung_id');
    }

    public function artikel()
    {
        return $this->belongsTo(MaterialanforderungArtikel::class, 'artikel_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function geklaertVon()
    {
        return $this->belongsTo(User::class, 'geklaert_von_id');
    }

    public function attachments()
    {
        return $this->hasMany(MaterialanforderungKommentarAnhang::class, 'kommentar_id');
    }
}
