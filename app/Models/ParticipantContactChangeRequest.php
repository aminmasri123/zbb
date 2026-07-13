<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantContactChangeRequest extends Model{use HasFactory;protected $fillable=['user_id','field','old_value','new_value','token_hash','expires_at','confirmed_at','cancelled_at','requested_ip','confirmed_ip','requested_user_agent'];protected $hidden=['token_hash'];protected $casts=['expires_at'=>'datetime','confirmed_at'=>'datetime','cancelled_at'=>'datetime'];public function user(){return $this->belongsTo(User::class);}}
