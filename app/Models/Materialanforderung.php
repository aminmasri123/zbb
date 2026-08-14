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
        'benoetigt_am' => 'date',
        'gesamtpreis' => 'decimal:2',
        'endsumme' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Materialanforderung $anforderung) {
            $anforderung->loadMissing('kommentare.attachments');
            foreach ($anforderung->kommentare as $kommentar) {
                foreach ($kommentar->attachments as $attachment) {
                    Storage::disk('local')->delete($attachment->path);
                }
            }
            Storage::disk('local')->deleteDirectory("materialanforderungen/{$anforderung->id}/kommentare");
        });
    }

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
        return $this->artikeln->sum(function ($position) {
            return $position->gesamtpreis + ($position->gesamtpreis * $position->mwst / 100);
        });
    }

    // Materialanforderung.php
    public function projekt()
    {
        return $this->belongsTo(Projekt::class);
    }
}
