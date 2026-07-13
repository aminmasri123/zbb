<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ParticipantApplication extends Model
{
    use HasFactory;
    protected $fillable = ['project_person_id','created_by_user_id','external_ref','title','employer','location','source_url','status','applied_at','next_action_at','notes','participant_package_approved_at','staff_package_approved_at','staff_package_approved_by_user_id'];
    protected $casts = ['applied_at' => 'date', 'next_action_at' => 'date', 'participant_package_approved_at' => 'datetime', 'staff_package_approved_at' => 'datetime'];
    public function participation() { return $this->belongsTo(ProjektHasPersonen::class, 'project_person_id'); }
    public function statusHistory() { return $this->hasMany(ParticipantApplicationStatusHistory::class, 'application_id')->orderByDesc('changed_at'); }
    public function documents() { return $this->belongsToMany(ParticipantPortalDocument::class, 'participant_application_documents', 'application_id', 'document_id')->withPivot('added_by_user_id')->withTimestamps(); }
}
