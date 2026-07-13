<?php

namespace App\Models;

use App\Models\Tage;
use App\Models\Gruppe;
use App\Models\Zeiten;
use App\Models\Personen;
use App\Models\Zeitraum;
use App\Models\Anwesenheitsstatuten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class GruppeHasPersonen extends Pivot
{
    use HasFactory;
        protected $table = 'gruppe_has_personens';
        protected $primaryKey = 'id'; // ✅ notwendig
        public $incrementing = true;  // ✅ Pivot darf eine eigene ID haben
        protected $keyType = 'int';   // ✅ integer ID
        protected $with = ['zeitgeplant', 'zeittatsaechlich', 'status', 'tag'];


    protected $fillable = ['id', 'personen_id', 'gruppe_id', 'user_id','tage_id','anwesenheitsstatuten_id', 'bemerkung', 'zeittatsaechlich_id', 'zeitgeplant_id'];

    protected static function booted(): void
    {
        static::creating(function (GruppeHasPersonen $assignment): void {
            $group = Gruppe::query()->with('projekt')->find($assignment->gruppe_id);
            if (!$group?->projekt) {
                return;
            }

            $isProjectParticipant = Personen::query()
                ->whereKey($assignment->personen_id)
                ->where('typ', 'teilnehmer')
                ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $group->projekt_id))
                ->exists();

            if (!$isProjectParticipant) {
                throw ValidationException::withMessages([
                    'teilnehmer' => 'Der Teilnehmer ist diesem Projekt nicht zugewiesen.',
                ]);
            }

            $maximum = $group->projekt->rule('max_group_participants');
            if ($maximum === null) {
                return;
            }

            $alreadyInGroup = static::query()
                ->where('gruppe_id', $group->id)
                ->where('personen_id', $assignment->personen_id)
                ->exists();

            if (!$alreadyInGroup && static::query()->where('gruppe_id', $group->id)->distinct()->count('personen_id') >= (int) $maximum) {
                throw ValidationException::withMessages([
                    'teilnehmer' => "Die maximale Gruppengröße von {$maximum} Teilnehmern ist erreicht.",
                ]);
            }
        });
    }


     public function teilnehmer() //muss durch teilnehmer ersetzt werden
    {
        return $this->belongsTo(Personen::class, 'personen_id')->where('typ', 'teilnehmer');
    }

    public function gruppe() //muss durch teilnehmer ersetzt werden
    {
        return $this->belongsTo(Gruppe::class, 'gruppe_id');
    }


    public function user()
    {
        return $this->belongsTo(Personen::class,'user_id', 'id' )->where('typ', 'mitarbeiter');
    }

    public function status()
    {
        return $this->belongsTo(Anwesenheitsstatuten::class, 'anwesenheitsstatuten_id');
    }

    public function tag()
    {
        return $this->belongsTo(Tage::class, 'tage_id');
    }




    public function zeitgeplant()
    {
        return $this->belongsTo(Zeiten::class, 'zeitgeplant_id');
    }

    public function zeittatsaechlich()
    {
        return $this->belongsTo(Zeiten::class, 'zeittatsaechlich_id');
    }
    public function zeitraume()
    {
        return $this->morphMany(Zeitraum::class, 'model');
    }






}
