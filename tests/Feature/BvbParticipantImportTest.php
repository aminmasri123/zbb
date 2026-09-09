<?php
namespace Tests\Feature;

use App\Models\{User,Projekt,Personen,ProjektHasPersonen};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BvbParticipantImportTest extends TestCase
{
    use RefreshDatabase;
    private int $importLocationId;
    private function context(): array
    {
        $user=User::factory()->create();
        $location=\App\Models\Standort::factory()->create();
        $user->standorte()->attach($location);$this->importLocationId=$location->id;
        $project=Projekt::factory()->create(['name'=>'BVB Reha']);
        $user->projekte()->attach($project);
        $user->update(['current_team_id'=>$project->id]);
        $this->grantTestPermission($user,'teilnehmer.import');
        return [$user,$project];
    }
    private function file(string $name='Müller', string $birth='12.03.2008', bool $empty=false): UploadedFile
    {
        $header="Nachname;Vorname;Namenszusatz;Geschlecht;Geburtsdatum;Straße;Nr.;PLZ;Ort;Adresszusatz;Telefon;Email;Telefax;Schulabschluss bei Übermittlung durch BA\r\n";
        $row="$name;Ada;von;w;$birth;Musterstraße;12a;01234;Musterstadt;Hinterhaus;0123456789;ada@example.test;0987654321;Hauptschulabschluss\r\n";
        return UploadedFile::fake()->createWithContent('bvb.csv', mb_convert_encoding($header.($empty?'':$row),'Windows-1252','UTF-8'));
    }
    public function test_preview_then_import_preserves_contacts_address_and_ba_source_without_duplicates(): void
    {
        [$user,$project]=$this->context();
        $file=$this->file();
        $preview=$this->actingAs($user)->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'preview'=>1])->assertOk()->assertJsonPath('profile','bvb_reha')->assertJsonPath('count',1);
        $this->assertDatabaseMissing('personens',['nachname'=>'Müller']);
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'confirmation'=>$preview->json('confirmation')])->assertOk()->assertJsonPath('success',true);
        $person=Personen::where('nachname','Müller')->firstOrFail();
        $this->assertSame('von',$person->namenszusatz);
        $this->assertDatabaseHas('adresses',['model_id'=>$person->id,'plz'=>'01234','strasse'=>'Musterstraße','hausnummer'=>'12a','zusatzinfo'=>'Hinterhaus']);
        $this->assertSame(['0123456789','ada@example.test','0987654321'],$person->kontaktes()->orderBy('id')->pluck('wert')->all());
        $participation=ProjektHasPersonen::where('personen_id',$person->id)->where('projekt_id',$project->id)->firstOrFail();
        $this->assertSame('Hauptschulabschluss',$participation->import_entry_data['school_qualification_at_entry']);
        $this->assertSame('Übermittlung durch BA laut Importdatei',$participation->import_entry_data['source']);
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'confirmation'=>$preview->json('confirmation')])->assertOk()->assertJsonPath('result.created',0)->assertJsonPath('result.deferred',1);
        $this->assertSame(1,Personen::where('nachname','Müller')->count());
    }
    public function test_confirmation_is_required_and_bound_to_exact_file_and_project(): void
    {
        [$user,$project]=$this->context();
        $file=$this->file();
        $this->actingAs($user)->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file])->assertUnprocessable();
        $preview=$this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'preview'=>1])->assertOk();
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$this->file('Anders'),'confirmation'=>$preview->json('confirmation')])->assertUnprocessable();
        $other=Projekt::factory()->create();$user->projekte()->attach($other);$user->update(['current_team_id'=>$other->id]);
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'confirmation'=>$preview->json('confirmation')])->assertUnprocessable();
        $this->assertDatabaseMissing('personens',['nachname'=>'Müller']);
    }
    public function test_header_only_and_invalid_calendar_date_are_rejected(): void
    {
        [$user]=$this->context();
        $this->actingAs($user)->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$this->file(empty:true),'preview'=>1])->assertUnprocessable();
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$this->file(birth:'31.02.2008'),'preview'=>1])->assertUnprocessable();
        $this->assertDatabaseMissing('personens',['nachname'=>'Müller']);
    }
    public function test_reordered_headers_are_mapped_and_duplicate_rows_are_flagged(): void
    {
        [$user]=$this->context();
        $header="Geburtsdatum;Nachname;Vorname;Geschlecht\n";
        $row="12.03.2008;Reihenfolge;Ada;w\n";
        $file=UploadedFile::fake()->createWithContent('standard.csv',$header.$row);
        $preview=$this->actingAs($user)->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$file,'preview'=>1])->assertOk();
        $this->assertSame('Ada',$preview->json('rows.0.values.0'));
        $this->assertSame('Reihenfolge',$preview->json('rows.0.values.1'));
        $duplicates=UploadedFile::fake()->createWithContent('doppelt.csv',$header.$row.$row);
        $this->postJson(route('teilnehmer.import'),['standort_id'=>$this->importLocationId,'file'=>$duplicates,'preview'=>1])->assertOk()->assertJsonPath('rows.1.match.status','file_duplicate');
        $this->assertDatabaseMissing('personens',['nachname'=>'Reihenfolge']);
    }

}
