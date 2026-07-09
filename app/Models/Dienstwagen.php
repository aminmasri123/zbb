<?php

namespace App\Models;

use App\Models\Personen;
use App\Models\Standort;
use App\Models\Dienstwagenfahrtenbuch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Dienstwagenkostenaufzeichnungen;
use App\Models\Dienstwagenwartungsaufzeichnungen;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dienstwagen extends Model
{
    use HasFactory;

	    protected $fillable = [
	        'typ',
	        'kennzeichen',
	        'marke',
	        'modell',
        'baujahr',
        'kraftstoffart',
        'kilometerstand',
	        'standort_id',
	        'status',
	        'naechste_wartung',
	        'bild_path',
	        'fin',
	        'hsn_tsn',
	        'tuev_bis',
	        'au_bis',
	        'oelwechsel_am',
	        'oelwechsel_km',
	        'versicherung_bis',
	        'steuer_faellig_am',
	        'inspektion_am',
	        'reifenwechsel_am',
	        'leasing_bis',
	        'tankkarte',
	        'notizen',
	    ];

        protected $appends = [
            'bild_url',
        ];

        protected $casts = [
            'naechste_wartung' => 'date',
            'tuev_bis' => 'date',
            'au_bis' => 'date',
            'oelwechsel_am' => 'date',
            'versicherung_bis' => 'date',
            'steuer_faellig_am' => 'date',
            'inspektion_am' => 'date',
            'reifenwechsel_am' => 'date',
            'leasing_bis' => 'date',
        ];

    public function scopeAktiv($query)
    {
        return $query->where('status','!=', 'passiv')->where('status','!=', 'verkäuft');
    }

    public function fahrer()
    {
        return $this->belongsToMany(Personen::class, 'dienstwagen_has_personens', 'dienstwagen_id', 'person_id');
    }

    public function wartungen()
    {
        return $this->hasMany(Dienstwagenwartungsaufzeichnungen::class);
    }

    public function kostanaufzeichnungen()
    {
        return $this->hasMany(Dienstwagenkostenaufzeichnungen::class);
    }

	    public function fahrten()
	    {
	        return $this->hasMany(Dienstwagenfahrtenbuch::class);
	    }

        public function buchungen()
        {
            return $this->hasMany(DienstwagenBuchung::class);
        }

        public function aktiveBuchungen()
        {
            return $this->hasMany(DienstwagenBuchung::class)
                ->whereNotIn('status', ['abgelehnt', 'storniert', 'abgeschlossen']);
        }

        public function meldungen()
        {
            return $this->hasMany(DienstwagenMeldung::class)->latest();
        }

        public function offeneMeldungen()
        {
            return $this->hasMany(DienstwagenMeldung::class)->where('status', '!=', 'erledigt');
        }

        public function verlaeufe()
        {
            return $this->hasMany(DienstwagenVerlauf::class)->latest('created_at');
        }

	    public function standort()
	    {
	        return $this->belongsTo(Standort::class);
	    }

        public function getBildUrlAttribute(): ?string
        {
            if (! $this->bild_path) {
                return null;
            }

            return Storage::disk('public')->url($this->bild_path);
        }
	}
