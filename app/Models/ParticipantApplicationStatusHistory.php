<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ParticipantApplicationStatusHistory extends Model
{
    use HasFactory;
    protected $fillable = ['application_id','from_status','to_status','changed_by_user_id','changed_at'];
    protected $casts = ['changed_at' => 'datetime'];
    public function application() { return $this->belongsTo(ParticipantApplication::class); }
}
