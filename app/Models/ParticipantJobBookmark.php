<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ParticipantJobBookmark extends Model
{
    use HasFactory;
    protected $fillable = ['person_id','external_ref','title','employer','location','source_url','published_at','source'];
    protected $casts = ['published_at' => 'date'];
    public function person() { return $this->belongsTo(Personen::class); }
}
