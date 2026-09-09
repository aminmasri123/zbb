<?php

namespace App\Console\Commands;

use App\Models\{AptitudeProfile,Projekt};
use App\Services\Aptitude\{AptitudeGroupSetup,AptitudeScoring,BvbAptitudeProfile};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallBvbAptitudeProfile extends Command
{
    protected $signature = 'aptitude:install-bvb {project} {--enable}';
    protected $description = 'Hinterlegt das BvB-Testprofil, ohne vorhandene Profile oder Testdaten zu ersetzen.';
    public function handle(AptitudeScoring $scoring): int
    {
        $definition = $scoring->validateDefinition(BvbAptitudeProfile::definition());
        DB::transaction(function () use ($definition) {
            $project = Projekt::whereKey($this->argument('project'))->lockForUpdate()->firstOrFail();
            if (! AptitudeProfile::where('projekt_id', $project->id)->exists()) {
                AptitudeProfile::create(['projekt_id'=>$project->id,'name'=>'BvB Reha – Eingangstest','version'=>1,'definition'=>$definition]);
            }
            if ($this->option('enable')) {
                if (! $project->featureEnabled('participant_management') || ! $project->featureEnabled('group_management')) throw new \RuntimeException('Teilnehmer und Gruppen müssen aktiviert sein.');
                $tabs=$project->participantProfileSettings();
                $tabs['enabled_tabs']=array_values(array_unique([...$tabs['enabled_tabs'],'eignungstests']));
                $project->update(['feature_settings'=>[...($project->feature_settings??[]),'aptitude_tests'=>true],'participant_profile_settings'=>$tabs]);
                app(AptitudeGroupSetup::class)->ensureArea($project);
            }
            $this->info('Testprofil für '.$project->name.' verfügbar. Es wurden keine Teilnehmerergebnisse angelegt.');
        });
        return self::SUCCESS;
    }
}
