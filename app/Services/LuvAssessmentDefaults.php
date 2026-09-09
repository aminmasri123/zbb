<?php

namespace App\Services;

use App\Models\{AptitudeAttempt, ProjektHasPersonen, PotenzialanalyseBericht};
use Carbon\Carbon;

class LuvAssessmentDefaults
{
    /** Call only after authorizing the participation. Saved test reports are draft suggestions, not approvals. */
    public function fields(ProjektHasPersonen $participation, string $type, string $until, ?string $from = null): array
    {
        $parts=[]; $sources=[]; $seen=[];
        $append=function ($category,$field,$label,$text,$source) use (&$parts,&$sources,$type) {
            if (blank($text)) return;
            $key='competence.'.$category.'.'.$field;
            if ($type==='Verlauf') $key=$field==='assessment'?'development.notes':'competence.'.$category.'.current_need';
            if ($type==='Abschluss') {
                if ($field==='assessment') return; // A test score is not a final outcome or a support need.
                $key='support.description';
            }
            $parts[$key][]=$label.': '.trim($text);
            $sources[$key][]=$source;
        };
        if ($participation->projekt->featureEnabled('aptitude_tests')) {
            $attempts=AptitudeAttempt::where('project_person_id',$participation->id)
                ->whereDate('tested_on','<=',$until)->orderByDesc('tested_on')->orderByDesc('id')->get();
            foreach ($attempts as $attempt) foreach ($attempt->results as $key=>$result) {
                $identity=$result['category'].'|'.$key;
                if (isset($seen[$identity])) continue;
                $seen[$identity]=true;
                $label=$key==='german'?'Deutschkenntnisse':$result['label'];
                foreach (['assessment','support_need'] as $field) $append($result['category'],$field,$label,$attempt->reports[$key][$field]??'',[
                    'origin'=>'Eignungstest','date'=>$attempt->tested_on->toDateString(),'status'=>$attempt->status,'id'=>$attempt->id,
                ]);
            }
        }
        if ($participation->projekt->supportsLuvPotentialAnalysis()) {
            $reports=PotenzialanalyseBericht::where('personen_id',$participation->personen_id)
                ->whereIn('status',['fertig','geprueft'])->whereDate('fertiggestellt_at','<=',$until)
                ->whereHas('gruppe',fn($q)=>$q->where('projekt_id',$participation->projekt_id))->get();
            foreach (PotenzialanalyseBericht::LUV_FOERDERBEDARF_BEREICHE as $key=>$definition) {
                $candidates=[];
                foreach ($reports as $report) {
                    $entry=$report->luv_foerderbedarfe[$key]??[];
                    if (empty($entry['freigegeben']) || !in_array($entry['status']??'', ['foerderbedarf','kein_foerderbedarf'],true)) continue;
                    $date=$entry['freigegeben_am']??null;
                    if (!$date) continue;
                    try {$date=Carbon::parse($date);} catch (\Throwable) {continue;}
                    if ($date->toDateString()>$until) continue;
                    $text=$entry['status']==='kein_foerderbedarf'?'Kein zusätzlicher Förderbedarf festgestellt.':($entry['foerderbedarf']??'');
                    if (blank($text)) continue;
                    $candidates[]=['date'=>$date,'text'=>$text,'assessment'=>$entry['begruendung']??'','id'=>$report->id];
                }
                usort($candidates,fn($a,$b)=>$b['date']->getTimestamp()<=>$a['date']->getTimestamp() ?: $b['id']<=>$a['id']);
                if ($latest=$candidates[0]??null) {
                    $source=['origin'=>'Potenzialanalyse','date'=>$latest['date']->toDateString(),'status'=>'approved','id'=>$latest['id']];
                    $append($key,'assessment',$definition['label'],$latest['assessment'],$source);
                    $append($key,'support_need',$definition['label'],$latest['text'],$source);
                }
            }
        }
        if (in_array($type,['Start','Verlauf'],true)) {
            $goal=app(\App\Services\Participants\CareerGoalLuvSource::class)->source($participation->id,$until);
            if ($goal) {
                $parts['integration.goal']=[$goal['text']];
                $sources['integration.goal']=[['origin'=>'Berufliches Ziel / Zielvereinbarung','date'=>$goal['documented_on'],'status'=>$goal['agreement_status'],'id'=>$goal['source_id']]];
            }
        }
        if($from&&($daily=app(DailyTaskLuvSummary::class)->entry($participation,$from,$until,$type))){
            $parts[$daily['field_key']][]=$daily['observation'];
            $sources[$daily['field_key']][]=['origin'=>'Tagesdokumentation','date'=>$from.' – '.$until,'status'=>'saved','id'=>$daily['source_id']];
        }
        return ['fields'=>array_map(fn($texts)=>implode("\n\n",array_unique($texts)),$parts),'sources'=>$sources];
    }
}
