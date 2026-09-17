<?php

namespace App\Models;

use App\Models\MaterialanforderungArtikel;
use App\Models\MaterialanforderungVergabevermerk;
use App\Models\User;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Materialanforderung extends Model
{
    use HasFactory;

    protected $fillable = [
        'preisart', 'versand_brutto', 'bestellnummer', 'standort_id', 'versand_netto', 'versand_mwst', 'lieferant_adresse',
        'lieferantenreferenz', 'approval_policy', 'revision', 'selected_offer_id', 'order_snapshot', 'bestellt_am',
        'projekt_id',
        'kostenstelle',
        'benoetigt_am',
        'prioritaet',
        'status',
        'gesamtpreis',
        'endsumme',
        'bemerkungen',
        'ersteller_id',
    ];

    protected $casts = [
        'approval_policy' => 'array', 'order_snapshot' => 'array', 'revision' => 'integer',
        'versand_brutto' => 'decimal:2', 'bestellt_am' => 'datetime', 'versand_netto' => 'decimal:2', 'versand_mwst' => 'decimal:2',
        'benoetigt_am' => 'date',
        'gesamtpreis' => 'decimal:2',
        'endsumme' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Materialanforderung $anforderung) {
            foreach ($anforderung->angebote as $offer) Storage::disk('local')->delete($offer->path);
            Storage::disk('local')->deleteDirectory("materialanforderungen/{$anforderung->id}/angebote");
            Storage::disk('local')->deleteDirectory("materialanforderungen/{$anforderung->id}/bestellschein");
            $anforderung->loadMissing('kommentare.attachments');
            foreach ($anforderung->kommentare as $kommentar) {
                foreach ($kommentar->attachments as $attachment) {
                    Storage::disk('local')->delete($attachment->path);
                }
            }
            Storage::disk('local')->deleteDirectory("materialanforderungen/{$anforderung->id}/kommentare");
        });
    }

    public function angebote() { return $this->hasMany(PurchaseOffer::class, 'anforderung_id'); }
    public function standort() { return $this->belongsTo(Standort::class); }

    public function vergabevermerke()
    {
        return $this->hasMany(MaterialanforderungVergabevermerk::class, 'anforderung_id');
    }

    public function vergabevermerk()
    {
        return $this->hasOne(MaterialanforderungVergabevermerk::class, 'anforderung_id');
    }
    // Beziehungen
    public function besteller()
    {
        return $this->belongsTo(User::class, 'ersteller_id');
    }

    public function artikeln()
    {
        return $this->hasMany(MaterialanforderungArtikel::class, 'anforderung_id');
    }

    

    public function genehmigungen()
    {
        return $this->hasMany(MaterialanforderungGenehmigung::class, 'anforderung_id');
    }

    public function kommentare()
    {
        return $this->hasMany(MaterialanforderungKommentar::class, 'anforderung_id');
    }

    // Berechne Gesamtsumme inkl. MwSt
    public function berechneEndsumme(): float
    {
        return app(\App\Services\Purchasing\PurchaseWorkflow::class)->totals(
            $this->artikeln->toArray(), $this->versand_netto, $this->versand_mwst, $this->versand_brutto
        )[1];
    }

    // Materialanforderung.php
    public function projekt()
    {
        return $this->belongsTo(Projekt::class);
    }
}
