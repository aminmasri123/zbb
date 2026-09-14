<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use App\Services\Bop\PaPreparationAttendanceTemplateExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\TestCase;

class PaPreparationTemplateExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_exports_the_selected_template_with_participants_date_and_signature(): void
    {
        [$user, $scope, $person] = $this->context();
        $path = storage_path(PaPreparationAttendanceTemplateExportService::TEMPLATE_PATH);
        $before = hash_file('sha256', $path);
        $source = IOFactory::load($path)->getActiveSheet();
        $response = $this->actingAs($user)->post(route('anwesenheitsliste.PA.preparation.export.template'), $scope + ['format' => 'xlsx']);
        $response->assertOk()->assertDownload();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame($source->getCell('A1')->getValue(), $sheet->getCell('A1')->getValue());
        $this->assertSame('Vorlagen-Testschule', $sheet->getCell('B2')->getValue());
        $this->assertSame('7.1', $sheet->getCell('B5')->getValue());
        $this->assertSame('2026-09-15', Date::excelToDateTimeObject($sheet->getCell('E6')->getValue())->format('Y-m-d'));
        $this->assertSame('=Muster', $sheet->getCell('B8')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B8')->getDataType());
        $this->assertSame('Mina', $sheet->getCell('C8')->getValue());
        $this->assertSame('w', $sheet->getCell('D8')->getValue());
        $this->assertSame('A1:E8', $sheet->getPageSetup()->getPrintArea());
        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('E8', $sheet->getDrawingCollection()[0]->getCoordinates());
        $this->assertNotEmpty($sheet->getHeaderFooter()->getImages());
        $this->assertSame($source->getHeaderFooter()->getOddFooter(), $sheet->getHeaderFooter()->getOddFooter());
        $this->assertSame($before, hash_file('sha256', $path));
        @unlink($response->baseResponse->getFile()->getPathname());
    }

    public function test_pdf_uses_template_content_and_removes_the_intermediate_file(): void
    {
        [$user, $scope] = $this->context();
        $response = $this->actingAs($user)->post(route('anwesenheitsliste.PA.preparation.export.template'), $scope + ['format' => 'pdf']);
        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $xlsxPath = substr($path, 0, -4).'.xlsx';
        $this->assertFileDoesNotExist($xlsxPath);
        $this->assertSame('pdf', $response->baseResponse->getFile()->getExtension());
        $text = (new \Smalot\PdfParser\Parser)->parseFile($path)->getText();
        $this->assertStringContainsString('Mina', $text);
        $this->assertStringContainsString('15.09.2026', $text);
        $this->assertStringContainsString('Teilnehmendenliste zum Nachweis der Vorbereitung BO-Tage', $text);
        @unlink($path);
    }

    public function test_template_export_is_restricted_to_preparation_and_authorized_projects(): void
    {
        [$user, $scope] = $this->context();
        $url = route('anwesenheitsliste.PA.preparation.export.template');
        $this->actingAs($user)->postJson($url, array_replace($scope, ['listType' => 'pa', 'format' => 'xlsx']))->assertUnprocessable();
        $otherSchool = Partner::create(['name' => 'Andere Schule']);
        $this->postJson($url, array_replace($scope, ['schuleId' => $otherSchool->id, 'format' => 'xlsx']))->assertNotFound();
        $this->postJson($url, array_replace($scope, ['klasse' => '7.2', 'format' => 'xlsx']))->assertUnprocessable();
        $user->revokePermissionTo('anwesenheit.abrechnung');
        $this->actingAs($user->fresh())->postJson($url, $scope + ['format' => 'xlsx'])->assertForbidden();
    }

    public static function pdfPaperFormats(): array
    {
        return ['A4' => ['A4'], 'A3' => ['A3']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pdfPaperFormats')]
    public function test_pdf_prints_all_participants_with_repeated_headers_and_page_numbers(string $format): void
    {
        [$user, $scope] = $this->context();
        foreach (range(2, 32) as $number) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Testperson'.$number, 'nachname' => 'Muster', 'geschlecht' => 'm']);
            PersonenIstSchueler::create(['person_id' => $person->id, 'schule_id' => $scope['schuleId'], 'schuljahr' => $scope['schuljahr'], 'teil' => '1', 'klasse' => '7.1']);
        }
        $response = $this->actingAs($user)->post(route('anwesenheitsliste.PA.preparation.export.template'), $scope + ['format' => 'pdf', 'exportFormat' => $format]);
        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $this->assertFileDoesNotExist(substr($path, 0, -4).'.xlsx');
            $pdf = (new \Smalot\PdfParser\Parser)->parseFile($path);
            $text = $pdf->getText();
            $this->assertStringContainsString('Mina', $text);
            foreach (range(2, 32) as $number) {
                $this->assertStringContainsString('Testperson'.$number, $text);
            }
            $this->assertCount($format === 'A3' ? 1 : 2, $pdf->getPages());
            foreach ($pdf->getPages() as $page) {
                $this->assertStringContainsString('Vorlagen-Testschule', $page->getText());
                $this->assertStringContainsString('Seite', $page->getText());
            }
        } finally {
            @unlink($path);
        }
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Vorlagen-Testschule']);
        DB::table('projekt_has_partners')->insert(['projekt_id' => $project->id, 'partner_id' => $school->id]);
        DB::table('projekt_has_personens')->insert(['projekt_id' => $project->id, 'personen_id' => $user->person_id, 'status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Mina', 'nachname' => '=Muster', 'geschlecht' => 'w']);
        PersonenIstSchueler::create(['person_id' => $person->id, 'schule_id' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '1', 'klasse' => '7.1']);
        $scope = ['schuleId' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '1', 'listType' => 'pa_preparation', 'exportMode' => 'klasse', 'klasse' => '7.1'];
        $day = ['id' => 'pa-vorbereitung-2026-09-15', 'date' => '2026-09-15', 'selected' => true, 'type' => 'preparation'];
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $this->actingAs($user)->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + ['payload' => [
            'version' => 1, 'form' => ['exportMode' => 'klasse', 'klasse' => '7.1', 'exportFormat' => 'A4'],
            'days' => [$day], 'selectedDayId' => $day['id'], 'signatures' => [$day['id'].':'.$person->id => $signature],
        ]])->assertOk();

        return [$user->fresh(), $scope, $person];
    }
}
