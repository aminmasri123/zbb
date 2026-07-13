<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ParticipantCvEntry extends Model{use HasFactory;protected $fillable=['person_id','type','title','organization','location','starts_at','ends_at','current','description','proficiency','sort_order'];protected $casts=['starts_at'=>'date','ends_at'=>'date','current'=>'boolean','sort_order'=>'integer'];}
