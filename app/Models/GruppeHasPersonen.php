<?php

namespace App\Models;

use App\Models\Tage;
use App\Models\Zeiten;
use App\Models\Personen;
use App\Models\Zeitraum;
use App\Models\Anwesenheitsstatuten;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GruppeHasPersonen extends Pivot
{
    use HasFactory;
        protected $table = 'gruppe_has_personens';
        protected $primaryKey = 'id'; // ✅ notwendig
        public $incrementing = true;  // ✅ Pivot darf eine eigene ID haben
        protected $keyType = 'int';   // ✅ integer ID
        protected $with = ['zeitgeplant', 'zeittatsaechlich', 'status', 'tag'];


    protected $fillable = ['id', 'personen_id', 'gruppe_id', 'user_id','tage_id','anwesenheitsstatuten_id', 'bemerkung', 'zeittatsaechlich_id', 'zeitgeplant_id'];


    public function person()
    {
        return $this->belongsTo(Personen::class, 'personen_id')->where('typ', 'teilnehmer');
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
