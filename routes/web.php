<?php

use App\Http\Controllers\AbschlusseController;
use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\AnwesenheitController;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\BaenkeController;
use App\Http\Controllers\BerechtigungController;
use App\Http\Controllers\BereichController;
use App\Http\Controllers\BopGruppeExportController;
use App\Http\Controllers\BopLegacyFunctionController;
use App\Http\Controllers\BriefController;
use App\Http\Controllers\DashbaordController;
use App\Http\Controllers\DienstwagenController;
use App\Http\Controllers\DienstwagenfahrtenbuchController;
use App\Http\Controllers\DienstwagenkostenController;
use App\Http\Controllers\DienstwagenreportsController;
use App\Http\Controllers\DienstwagenwartungController;
use App\Http\Controllers\DokumenteController;
use App\Http\Controllers\EinteilungParameterController;
use App\Http\Controllers\ExportExcelController;
use App\Http\Controllers\ExportWordController;
use App\Http\Controllers\FahrtartenController;
use App\Http\Controllers\FahrtkostenAbrechnenController;
use App\Http\Controllers\FahrtkostensaetzeController;
use App\Http\Controllers\GeraetausgabeController;
use App\Http\Controllers\GeraetController;
use App\Http\Controllers\GeraetrueckgabeController;
use App\Http\Controllers\GruppeController;
use App\Http\Controllers\GruppeHasTeilnehmerController;
use App\Http\Controllers\KlassenbuchController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\KostenstelleController;
use App\Http\Controllers\MaterialanforderungController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotizController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PersonenHasBildungsmassnahmenController;
use App\Http\Controllers\ProjektBopController;
use App\Http\Controllers\ProjektController;
use App\Http\Controllers\ProjektHasPersonenController;
use App\Http\Controllers\ProjektHasTeilnehmerController;
use App\Http\Controllers\ProjektHasTeilnehmerLuvController;
use App\Http\Controllers\RaumlichkeitenController;
use App\Http\Controllers\RoleDataAccessController;
use App\Http\Controllers\RolleController;
use App\Http\Controllers\SchuleController;
use App\Http\Controllers\StandortController;
use App\Http\Controllers\TeilnehmerController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');




Route::post('/set-locale', function () {
    request()->validate(['locale' => 'required|string']);
    session(['locale' => request('locale')]);
    return response()->json(['success' => true]);
});




// Geschützte Routen
Route::get('/bereichsauswahl/zugang/{token}', [ProjektBopController::class, 'bereichsauswahlSelfShow'])->name('bereichsauswahl.self.show');
Route::post('/bereichsauswahl/zugang/{token}/code', [ProjektBopController::class, 'bereichsauswahlSelfVerify'])->name('bereichsauswahl.self.verify');
Route::post('/bereichsauswahl/zugang/{token}', [ProjektBopController::class, 'bereichsauswahlSelfStore'])->name('bereichsauswahl.self.store');
Route::get('/bereichsauswahl/zugang/{token}/danke', [ProjektBopController::class, 'bereichsauswahlSelfThanks'])->name('bereichsauswahl.self.thanks');

//Route::middleware(['auth', 'verified', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {

Route::middleware(['auth', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {

    Route::post('/benutzer/theme', function () {
        $data = request()->validate([
            'theme' => ['required', 'string', 'in:air,dark,womanly,champion,sprint,arena,pulse,trail,bazaar,vital'],
        ]);

        request()->user()->forceFill([
            'theme' => $data['theme'],
        ])->save();

        return response()->json([
            'success' => true,
            'theme' => $data['theme'],
        ]);
    })->name('user.theme.update');

    Route::get('/dashboard', [DashbaordController::class, 'dashboard'])->name('dashboard');
    Route::prefix('apps')->name('apps.')->group(function () {
        Route::get('/', [AppsController::class, 'index'])->name('index');

        Route::get('/dateimanager', [AppsController::class, 'files'])->name('files');
        Route::post('/dateimanager/ordner', [AppsController::class, 'createFolder'])->name('files.folder.store');
        Route::post('/dateimanager/upload', [AppsController::class, 'uploadFile'])->name('files.upload');
        Route::get('/dateimanager/download/{file}', [AppsController::class, 'downloadFile'])->name('files.download');
        Route::put('/dateimanager/{file}', [AppsController::class, 'updateFile'])->name('files.update');
        Route::delete('/dateimanager/{file}', [AppsController::class, 'deleteFile'])->name('files.destroy');
        Route::post('/dateimanager/{file}/mail', [AppsController::class, 'mailFile'])->name('files.mail');

        Route::get('/kalender', [AppsController::class, 'calendar'])->name('calendar');
        Route::get('/kalender/events', [AppsController::class, 'calendarEvents'])->name('calendar.events');
        Route::get('/kalender/export', [AppsController::class, 'exportCalendar'])->name('calendar.export');
        Route::post('/kalender/import/vorschau', [AppsController::class, 'previewCalendarImport'])->name('calendar.import.preview');
        Route::post('/kalender/import/bestaetigen', [AppsController::class, 'confirmCalendarImport'])->name('calendar.import.confirm');
        Route::post('/kalender/kalender', [AppsController::class, 'storeCalendarCalendar'])->name('calendar.calendars.store');
        Route::post('/kalender/farben', [AppsController::class, 'storeCalendarStyle'])->name('calendar.styles.store');
        Route::post('/kalender', [AppsController::class, 'storeCalendar'])->name('calendar.store');
        Route::post('/kalender/{event}/move', [AppsController::class, 'moveCalendar'])->name('calendar.move');
        Route::post('/kalender/{event}/copy', [AppsController::class, 'copyCalendar'])->name('calendar.copy');
        Route::put('/kalender/{event}', [AppsController::class, 'updateCalendar'])->name('calendar.update');
        Route::delete('/kalender/{event}', [AppsController::class, 'destroyCalendar'])->name('calendar.destroy');

        Route::get('/kontakte', [AppsController::class, 'contacts'])->name('contacts');
        Route::post('/kontakte', [AppsController::class, 'storeContact'])->name('contacts.store');
        Route::put('/kontakte/{contact}', [AppsController::class, 'updateContact'])->name('contacts.update');
        Route::delete('/kontakte/{contact}', [AppsController::class, 'destroyContact'])->name('contacts.destroy');

        Route::get('/taskmanager', [AppsController::class, 'tasks'])->name('tasks');
        Route::post('/taskmanager', [AppsController::class, 'storeTask'])->name('tasks.store');
        Route::put('/taskmanager/{task}', [AppsController::class, 'updateTask'])->name('tasks.update');
        Route::delete('/taskmanager/{task}', [AppsController::class, 'destroyTask'])->name('tasks.destroy');
        Route::post('/taskmanager/workflows', [AppsController::class, 'storeTaskWorkflowTemplate'])->name('tasks.workflows.store');
        Route::post('/taskmanager/workflows/{template}/apply', [AppsController::class, 'applyTaskWorkflowTemplate'])->name('tasks.workflows.apply');
        Route::delete('/taskmanager/workflows/{template}', [AppsController::class, 'destroyTaskWorkflowTemplate'])->name('tasks.workflows.destroy');

        Route::get('/popups', [AppsController::class, 'popups'])->name('popups');
        Route::post('/popups', [AppsController::class, 'storePopup'])->name('popups.store');
        Route::put('/popups/{popup}', [AppsController::class, 'updatePopup'])->name('popups.update');
        Route::delete('/popups/{popup}', [AppsController::class, 'destroyPopup'])->name('popups.destroy');

        Route::post('/teilen/{type}/{id}', [AppsController::class, 'share'])->name('share');
    });
    Route::get('/organisation', function () {return Inertia::render('Dashboards/Organisation');})->name('organisation.index');
    Route::get('/ressourcen', function () {return Inertia::render('Dashboards/Ressourcen');})->name('ressourcen.index');
    Route::get('/finanzen', function () { return Inertia::render('Dashboards/Finanzen');})->name('finanzen.index');

    //Schuld
    Route::get('/schule', [SchuleController::class, 'index'])->name('schule.index')->can('schule.index');;

    //Standort
    Route::get('/standort', [StandortController::class, 'index'])->name('standort.index');
    Route::post('/standort/anlegen', [StandortController::class, 'store'])->name('standort.store');
    Route::delete('/standort/{id}', [StandortController::class, 'destroy'])->name('standort.destroy');
    Route::put('/standort/{id}', [StandortController::class, 'update'])->name('standort.update');

    // Personal
    Route::get('ressourcen/personal', [PersonalController::class, 'index'])->name('personal.index');
    Route::get('ressourcen/personal/edit/{id}', [PersonalController::class, 'edit'])->name('personal.edit');
    Route::put('ressourcen/personal/update/{user}', [PersonalController::class, 'update'])->name('personal.update');

    Route::post('/toggleCheck', [UserController::class, 'check'])->name('user.check');

    //Einstellung -- Rolle
    Route::get('/berechtigung/{id?}', [BerechtigungController::class, 'index'])->name('berechtigung.index')->can('berechtigung.index');
    Route::post('/berechtigungZuweisen', [BerechtigungController::class, 'berechtigungZuweisen'])->name('berechtigung.zuweisen')->can('berechtigung.update');
    Route::put('/rolle/{role}/datenzugriff', [RoleDataAccessController::class, 'update'])->name('rolle.data-access.update')->can('berechtigung.update');

    Route::delete('/rolle/loeschen/{id}', [RolleController::class, 'destroy'])->name('rolle.destroy');
    Route::post('/rolle/anlegen', [RolleController::class, 'store'])->name('rolle.store');

    //Benutzer
    Route::get('/benutzer', [UserController::class, 'index'])->name('user.index')->can('benutzer.index');
    Route::get('/benutzer/anlegen', [UserController::class, 'create'])->name('user.create')->can('benutzer.store');
    Route::post('/benutzer/anlegen', [UserController::class, 'store'])->name('user.store')->can('benutzer.store');
    Route::delete('/benutzer/entfernen/{id}', [UserController::class, 'destroy'])->name('user.destroy')->can('benutzer.destroy');
    Route::post('/benutzer/projekt/switch', [UserController::class, 'switch'])->name('projekt.switch');
    Route::get('/benutzer/edit/{id}', [UserController::class, 'edit'])->name('user.edit')->can('benutzer.update');
    Route::put('/benutzer/update/{user}', [UserController::class, 'update'])->name('user.update')->can('benutzer.update');

    //Profile-Benutzer
    Route::get('/user/profile/{id}', [UserController::class, 'show'])->name('user.profil');

    //Bereiche
    Route::get('/bereich', [BereichController::class, 'index'])->name('bereich.index');
    Route::get('/bereich/ajaxFresh', [BereichController::class, 'indexAjaxFresh'])->name('bereich.indexAjaxFresh');
    Route::put('/bereich/{id}', [BereichController::class, 'update'])->name('bereich.update');
    Route::post('/bereich/anlegen', [BereichController::class, 'store'])->name('bereich.store');
    Route::delete('/bereiche/{id}', [BereichController::class, 'destroy'])->name('bereich.destroy');

    //Abteilungen
    Route::get('/abteilung', [AbteilungController::class, 'index'])->name('abteilung.index');
    Route::get('/abteilung/ajaxFresh', [AbteilungController::class, 'indexAjaxFresh'])->name('abteilung.indexAjaxFresh');
    Route::post('/abteilung/anlegen', [AbteilungController::class, 'store'])->name('abteilung.store');
    Route::delete('/abteilungen/{id}', [AbteilungController::class, 'destroy'])->name('abteilung.destroy');
    Route::put('/abteilung/update/{abteilung}', [AbteilungController::class, 'update'])->name('abteilung.update');

    //Projekte
    Route::get('/projekt', [ProjektController::class, 'index'])->name('projekt.index');
    Route::get('/projekt/ajaxFresh', [ProjektController::class, 'indexAjaxFresh'])->name('projekt.indexAjaxFresh');
    Route::get('/projekt/{id}', [ProjektController::class, 'show'])->name('projekt.show');
    Route::post('/projekt/anlegen', [ProjektController::class, 'store'])->name('projekt.store');
    Route::put('/projekt/{projekt}/dokumente', [ProjektController::class, 'updateDokumente'])->name('projekt.dokumente.update');
    Route::put('/projekt/{id}', [ProjektController::class, 'update'])->name('projekt.update');
    Route::delete('/projekt/{id}', [ProjektController::class, 'destroy'])->name('projekt.destroy');

    // Export-Vorlagen / Dokumentenmanager
    Route::get('/dokumente', [DokumenteController::class, 'index'])->name('dokumente.index');
    Route::post('/dokumente', [DokumenteController::class, 'store'])->name('dokumente.store');
    Route::get('/dokumente/{dokument}/download', [DokumenteController::class, 'download'])->name('dokumente.download');
    Route::put('/dokumente/{dokument}', [DokumenteController::class, 'update'])->name('dokumente.update');
    Route::post('/dokumente/kategorien', [DokumenteController::class, 'storeKategorie'])->name('dokumente.kategorien.store');
    Route::put('/dokumente/projekt-kategorien/{projekt}', [DokumenteController::class, 'updateProjektKategorien'])->name('dokumente.projekt-kategorien.update');

    //Gruppe
    Route::get('/gruppe', [GruppeController::class, 'index'])->name('gruppe.index');
    Route::put('/gruppe/{id}', [GruppeController::class, 'update'])->name('gruppe.update');
    Route::delete('/gruppe/{id}', [GruppeController::class, 'destroy'])->name('gruppe.destroy');
    Route::post('/gruppe/anlegen', [GruppeController::class, 'store'])->name('gruppe.store');
    Route::get('/gruppe/{gruppe}/export/serienbrief/{dokument}', [ExportWordController::class, 'gruppeSerienbrief'])->name('gruppe.export.serienbrief');
    Route::get('/gruppe/{gruppe}/bop-export/namensschilder', [BopGruppeExportController::class, 'namensschilder'])->name('gruppe.bop.export.namensschilder');
    Route::get('/gruppe/{gruppe}/bop-export/anwesenheitsliste', [BopGruppeExportController::class, 'anwesenheitsliste'])->name('gruppe.bop.export.anwesenheitsliste');
    Route::get('/gruppe/{gruppe}/bop-export/hausordnung', [BopGruppeExportController::class, 'hausordnung'])->name('gruppe.bop.export.hausordnung');
    Route::get('/gruppe/{gruppe}/bop-export/berufsfelderprobung', [BopGruppeExportController::class, 'berufsfelderprobung'])->name('gruppe.bop.export.berufsfelderprobung');
    Route::get('/gruppe/{gruppe}/bop-export/auswertungsbogen-bop', [BopGruppeExportController::class, 'auswertungsbogenBop'])->name('gruppe.bop.export.auswertungsbogen-bop');
    Route::get('/gruppe/{gruppe}/bop-export/toilettennutzungsliste', [BopGruppeExportController::class, 'toilettennutzungsliste'])->name('gruppe.bop.export.toilettennutzungsliste');
    Route::get('/gruppe/{gruppe}/bop-export/zertifikat-pobo', [BopGruppeExportController::class, 'zertifikatPobo'])->name('gruppe.bop.export.zertifikat-pobo');
    Route::get('/gruppe/{gruppe}/bop-export/teilnahme-pobo', [BopGruppeExportController::class, 'teilnahmePobo'])->name('gruppe.bop.export.teilnahme-pobo');
    Route::get('/gruppe/{gruppe}/bop-export/zertifikat-pa', [BopGruppeExportController::class, 'zertifikatPa'])->name('gruppe.bop.export.zertifikat-pa');
    Route::get('/gruppe/{gruppe}/bop-export/teilnahme-pa', [BopGruppeExportController::class, 'teilnahmePa'])->name('gruppe.bop.export.teilnahme-pa');
    Route::get('/gruppe/{gruppe}/bop-export/auswertungsbogen-pa', [BopGruppeExportController::class, 'auswertungsbogenPa'])->name('gruppe.bop.export.auswertungsbogen-pa');

    //GruppeHasTeilnehmer
    Route::get('/gruppehasteilnehmer/{id}', [GruppeHasTeilnehmerController::class, 'show'])->name('gruppeHasTeilnehmer.show');
    Route::post('/gruppehasteilnehmer/anlegen', [GruppeHasTeilnehmerController::class, 'store'])->name('gruppeHasTeilnehmer.store');

    Route::delete('/gruppehasteilnehmer/gruppe/{gruppe}/teilnehmer/{personen}', [GruppeHasTeilnehmerController::class, 'destroyTeilnehmer'])->name('gruppeHasTeilnehmer.destroyTeilnehmer');
    Route::delete('/gruppehasteilnehmer/entfernen/{id}', [GruppeHasTeilnehmerController::class, 'destroy'])->name('gruppeHasPersonen.destroy');

    //Klassenbuch
    Route::get('/klassenbuecher', [KlassenbuchController::class, 'index'])->name('klassenbuch.index');
    Route::post('/klassenbuecher', [KlassenbuchController::class, 'store'])->name('klassenbuch.store');
    Route::get('/klassenbuecher/{klassenbuch}', [KlassenbuchController::class, 'show'])->name('klassenbuch.show');
    Route::get('/klassenbuecher/{klassenbuch}/wochen/{woche}', [KlassenbuchController::class, 'woche'])->name('klassenbuch.woche.show');
    Route::post('/klassenbuecher/{klassenbuch}/wochen/{woche}/eintraege', [KlassenbuchController::class, 'storeEintrag'])->name('klassenbuch.eintrag.store');
    Route::delete('/klassenbuecher/{klassenbuch}/wochen/{woche}/eintraege/{eintrag}', [KlassenbuchController::class, 'destroyEintrag'])->name('klassenbuch.eintrag.destroy');
    Route::post('/klassenbuecher/{klassenbuch}/wochen/{woche}/einreichen', [KlassenbuchController::class, 'submit'])->name('klassenbuch.woche.submit');
    Route::post('/klassenbuecher/{klassenbuch}/wochen/{woche}/pruefen', [KlassenbuchController::class, 'review'])->name('klassenbuch.woche.review');
    Route::post('/klassenbuecher/{klassenbuch}/wochen/{woche}/kommentare', [KlassenbuchController::class, 'storeKommentar'])->name('klassenbuch.kommentar.store');
    Route::put('/klassenbuecher/{klassenbuch}/wochen/{woche}/kommentare/{kommentar}', [KlassenbuchController::class, 'updateKommentar'])->name('klassenbuch.kommentar.update');

    //Teilnehmer
    Route::get('/teilnehmer', [TeilnehmerController::class, 'index'])->name('teilnehmer.index')->can('teilnehmer.index');
    Route::get('/teilnehmer/anlegen', function () { return Inertia::render('Teilnehmer/CreateTeilnehmer'); })->name('teilnehmer.create')->can('teilnehmer.store');
    Route::post('/teilnehmer/anlegen', [TeilnehmerController::class, 'store'])->name('teilnehmer.store')->can('teilnehmer.store');
    Route::post('/teilnehmer/import', [TeilnehmerController::class, 'import'])->name('teilnehmer.import')->can('teilnehmer.store');
    Route::delete('/teilnehmer/entfernen', [TeilnehmerController::class, 'bulkDestroy'])->name('teilnehmer.bulkDestroy')->can('teilnehmer.destroy');
    Route::delete('/teilnehmer/entfernen/{id}', [TeilnehmerController::class, 'destroy'])->name('teilnehmer.destroy')->can('teilnehmer.destroy');
    Route::get('/teilnehmer/bearbeiten/{id}', [TeilnehmerController::class, 'show'])->name('teilnehmer.edit')->can('teilnehmer.update');
    Route::patch('/teilnehmer/update/{id}', [TeilnehmerController::class, 'update'])->name('teilnehmer.update')->can('teilnehmer.update');
    Route::patch('/teilnehmer/{person}/sozialdaten', [TeilnehmerController::class, 'updateSozialdaten'])->name('person.sozialdaten.update')->can('teilnehmer.update');
    Route::get('/teilnehmer/{id}', [TeilnehmerController::class, 'indexNachProjekt'])->name('teilnehmer.projekt.index')->can('teilnehmer.index');

    // Personen Has Abschluss
    Route::post('/teilnehmer/abschluss/anlegen', [AbschlusseController::class, 'store'])->name('abschluss.store')->can('teilnehmer.update');
    Route::delete('/teilnehmer/abschluss/entfernen/{id}', [AbschlusseController::class, 'destroy'])->name('abschluss.destroy')->can('teilnehmer.update');

    // Personen Has Praktikum
    Route::post('/teilnehmer/praktikum/anlegen', [PersonenHasBildungsmassnahmenController::class, 'store'])->name('teilnehmer.praktikum.store')->can('teilnehmer.update');

    //Räumlichkeiten
    Route::get('/ressourcen/standort/raeumlichkeiten/', [RaumlichkeitenController::class, 'index'])->name('raeumlichkeiten.index');
    Route::post('/ressourcen/standort/raeumlichkeiten/anlegen', [RaumlichkeitenController::class, 'store'])->name('raeumlichkeiten.store');
    Route::put('/ressourcen/standort/raeumlichkeiten/update/{id}', [RaumlichkeitenController::class, 'update'])->name('raeumlichkeiten.update');
    Route::delete('/ressourcen/standort/raeumlichkeiten/entfernen/{id}', [RaumlichkeitenController::class, 'destroy'])->name('raeumlichkeiten.destroy');
    Route::post('/ressourcen/standort/raeumlichkeiten/{raum}/meldung', [RaumlichkeitenController::class, 'storeMeldung'])->name('raeumlichkeiten.meldung.store');
    Route::put('/ressourcen/standort/raeumlichkeiten/meldung/{meldung}', [RaumlichkeitenController::class, 'updateMeldung'])->name('raeumlichkeiten.meldung.update');

    //Anwesenheiten
    Route::post('/anwesenheit/speichern', [AnwesenheitController::class, 'store'])->name('anwesenheit.store');
    Route::delete('/anwesenheit/entfernen/{id}', [AnwesenheitController::class, 'destroy'])->name('anwesenheit.destroy');
    Route::post('/anwesenheit/update', [AnwesenheitController::class, 'update'])->name('anwesenheit.update');

    //Kontakte
    Route::delete('/teilnehmer/kontakt/entfernen/{id}', [KontaktController::class, 'destroy'])->name('kontakt.destroy')->can('teilnehmer.update');
    Route::post('/teilnehmer/kontakt/anlegen', [KontaktController::class, 'store'])->name('kontakt.store')->can('teilnehmer.update');

    //Adresse
    Route::post('/teilnehmer/adresse/anlegen', [AdresseController::class, 'store'])->name('adresse.store')->can('teilnehmer.update');
    Route::delete('/teilnehmer/adresse/entfernen/{id}', [AdresseController::class, 'destroy'])->name('adresse.destroy')->can('teilnehmer.update');

    //ProjektHasTeilnehmer
    Route::post('/teilnehmer/projekt/anlegen', [ProjektHasTeilnehmerController::class, 'store'])->name('projekthasteilnehmer.store')->can('teilnehmer.update');
    Route::put('/teilnehmer/projekt/edit', [ProjektHasTeilnehmerController::class, 'update'])->name('projekthasteilnehmer.update')->can('teilnehmer.update');

    //ProjektHasTeilnehmerLuv
    Route::post('/teilnehmer/projekt/luv/anlegen', [ProjektHasTeilnehmerLuvController::class, 'store'])->name('projekthasteilnehmer.luv.store')->can('teilnehmer.update');
    Route::put('/teilnehmer/projekt/luv/edit', [ProjektHasTeilnehmerLuvController::class, 'update'])->name('projekthasteilnehmer.luv.update')->can('teilnehmer.update');
    Route::delete('/teilnehmer/projekt/luv/entfernen/{id}', [ProjektHasTeilnehmerLuvController::class, 'destroy'])->name('projekthasteilnehmer.luv.destroy')->can('teilnehmer.update');
    Route::get('/teilnehmer/projekt/luv/export/{id}', [ProjektHasTeilnehmerLuvController::class, 'export'])->name('projekthasteilnehmer.luv.export')->can('teilnehmer.index');

    //ProjektHasPersonen
    Route::post('/personen/projekt/zuweisen', [ProjektHasPersonenController::class, 'store'])->name('projekthaspersonen.store')->can('benutzer.update');
    Route::delete('/personen/projekt/entfernen/{id}', [ProjektHasPersonenController::class, 'destroy'])->name('projekthaspersonen.destroy')->can('benutzer.update');

    //Teilnehmer Bank
    Route::post('/teilnehmer/bank/anlegen', [BaenkeController::class, 'store'])->name('bank.store')->can('teilnehmer.update');
    Route::delete('/teilnehmer/bank/entfernen/{id}', [BaenkeController::class, 'destroy'])->name('bank.destroy')->can('teilnehmer.update');

    //Partner
    Route::get('/organisation/partner', [PartnerController::class, 'index'])->name('partner.index');
    Route::get('/partner', [PartnerController::class, 'index'])->name('dashboard.partner.index');
    Route::post('/organisation/partner/anlegen', [PartnerController::class, 'store'])->name('partner.store');
    Route::delete('/organisation/partner/entfernen/{id}', [PartnerController::class, 'destroy'])->name('partner.destroy');
    Route::put('/organisation/partner/edit/{id}', [PartnerController::class, 'update'])->name('partner.update');
    Route::get('/organisation/partner/ajax/fresh', [PartnerController::class, 'indexAjaxFresh'])->name('partner.indexAjaxFresh');

    //Kostenstelle
    Route::get('/kostenstelle', [KostenstelleController::class, 'index'])->name('kostenstelle.index');
    Route::post('/kostenstelle/anlegen', [KostenstelleController::class, 'store'])->name('kostenstelle.store');

    Route::get('/design/responsive', function () {
        return Inertia::render('Design/Responsive');
    })->name('responsive');

    //Notification
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    //Brief
        Route::post('/brief', [BriefController::class, 'store'])->name('brief.store');
        Route::post('/brief/share', [BriefController::class, 'share'])->name('brief.share');
        Route::delete('/brief/delete/{id}', [BriefController::class, 'destroy'])->name('brief.destroy');
        Route::delete('/brief/shared/delete/{id}', [BriefController::class, 'sharedDestroy'])->name('briefShared.destroy');

    //Notizen
        Route::post('/notizen', [NotizController::class, 'store'])->name('notizen.store');
        Route::delete('/notizen/delete/{id}', [NotizController::class, 'destroy'])->name('notizen.destroy');

        //Export
        Route::get('/teilnehmer/export/stammblatt/{teilnehmerId}/{projektId}', [ExportExcelController::class, 'esfStammblatt'])->name('export.excel.esfStammblatt');

    //Fahrtarten
    Route::get('/finanzen/fahrtarten', [FahrtartenController::class, 'index'])->name('fahrtarten.index');
    Route::post('/finanzen/fahrtarten/anlegen', [FahrtartenController::class, 'store'])->name('fahrtarten.store');
    Route::delete('/finanzen/fahrtarten/delete/{id}', [FahrtartenController::class, 'destroy'])->name('fahrtarten.destroy');

    //Fahrtkosten
    Route::get('/finanzen/fahrtkosten', [FahrtkostensaetzeController::class, 'index'])->name('fahrtkosten.index');
    Route::post('/finanzen/fahrtkosten/anlegen', [FahrtkostensaetzeController::class, 'store'])->name('fahrtkosten.store');
    Route::delete('/finanzen/fahrtkosten/delete/{id}', [FahrtkostensaetzeController::class, 'destroy'])->name('fahrtkosten.destroy');

    //Teilnehmer Farhten
    Route::post('/fahrtkosten/Abrechnen/anlegen', [FahrtkostenAbrechnenController::class, 'store'])->name('fahrtkostenAbrechnung.store');
    Route::delete('/fahrtkosten/Abrechnen/delete/{id}', [FahrtkostenAbrechnenController::class, 'destroy'])->name('fahrtkostenAbrechnung.destroy');


    Route::prefix('ressourcen')->name('dienstwagen.')->group(function () {
        // Fahrzeuge
        Route::get('/dienstwagen', [DienstwagenController::class, 'index'])->name('index');
        Route::get('/dienstwagen/edit/{id}', [DienstwagenController::class, 'edit'])->name('edit');
        Route::get('/dienstwagen/create', [DienstwagenController::class, 'create'])->name('create');
        Route::post('/dienstwagen', [DienstwagenController::class, 'store'])->name('store');
        Route::delete('/dienstwagen/{wagen}', [DienstwagenController::class, 'destroy'])->name('destroy');
        Route::put('dienstwagen/update/{id}', [DienstwagenController::class, 'update'])->name('update');

        // Fahrer
        Route::get('/drivers', [DienstwagenController::class, 'index'])->name('drivers.index');

        // Wartung
        Route::get('/wartung', [DienstwagenwartungController::class, 'index'])->name('wartung.index');
        Route::post('/wartung', [DienstwagenwartungController::class, 'store'])->name('wartung.store');
        Route::put('/wartung/edit/{id}', [DienstwagenwartungController::class, 'update'])->name('wartung.update');
        Route::delete('/wartung/{id}', [DienstwagenwartungController::class, 'destroy'])->name('wartung.destroy');

        // Kosten
        Route::get('/kosten', [DienstwagenkostenController::class, 'index'])->name('kosten.index');
        Route::post('/kosten', [DienstwagenkostenController::class, 'store'])->name('kosten.store');

        // Berichte
        Route::get('/dienstwagen/reports', [DienstwagenreportsController::class, 'index'])->name('reports.index');

        // Fahrtenbuch
        Route::get('/fahrtenbuch', [DienstwagenfahrtenbuchController::class, 'index'])->name('fahrtenbuch.index');
        Route::post('/fahrtenbuch', [DienstwagenfahrtenbuchController::class, 'store'])->name('fahrtenbuch.store');
        Route::put('/fahrtenbuch/edit/{id}', [DienstwagenfahrtenbuchController::class, 'update'])->name('fahrtenbuch.update');
        Route::delete('/fahrtenbuch/{id}', [DienstwagenfahrtenbuchController::class, 'destroy'])->name('fahrtenbuch.destroy');
        Route::get('/fahrtenbuch/report', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchReport'])->name('fahrtenbuch.report');
        Route::get('/fahrtenbuch/report/pdf', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchPDF'])->name('fahrtenbuch.report.pdf');
        Route::get('/fahrtenbuch/report/excel', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchExcel'])->name('fahrtenbuch.report.excel');
    });
    //End Prefix Ressourcen

  //Dokumente Exportieren
    Route::get('/export/dokument/{id}', [ExportWordController::class, 'info_teilnehmende'])->name('export.info_teilnehmende');
    Route::get('/export/dokument/bildungsvertrag_inteqra/{id}', [ExportWordController::class, 'bildungsvertrag_inteqra'])->name('export.bildungsvertrag_inteqra');
    Route::get('/export/dokument/datenschutzhinweis_art13/{id}', [ExportWordController::class, 'datenschutzhinweis_art13'])->name('export.datenschutzhinweis_art13');
    Route::get('/export/dokument/einverstaendnis_datenschutz_esf/{id}', [ExportWordController::class, 'einverstaendnis_datenschutz_esf'])->name('export.einverstaendnis_datenschutz_esf');
    Route::get('/export/dokument/fehlzeitenkonzept/{id}', [ExportWordController::class, 'fehlzeitenkonzept'])->name('export.fehlzeitenkonzept');
    Route::get('/export/dokument/einverstaendnis_foto/{id}', [ExportWordController::class, 'einverstaendnis_foto'])->name('export.einverstaendnis_foto');
    Route::get('/export/dokument/einverstaendnis_elternarbeit/{id}', [ExportWordController::class, 'einverstaendnis_elternarbeit'])->name('export.einverstaendnis_elternarbeit');
    Route::get('/export/dokument/edv_nutzungsvereinbarung/{id}', [ExportWordController::class, 'edv_nutzungsvereinbarung'])->name('export.edv_nutzungsvereinbarung');
    Route::get('/export/dokument/hausordnung_v1/{id}', [ExportWordController::class, 'hausordnung_v1'])->name('export.hausordnung_v1');


    Route::get('/export/dokument/anwesenheitslite_V1/{id}', [ExportExcelController::class, 'anwesenheitslite_V1'])->name('export.anwesenheitslite_V1');
    Route::get('/export/dokument/anwesenheitliste_monat_projekt_gruppe/{id}', [ExportExcelController::class, 'anwesenheitliste_monat_projekt_gruppe'])->name('export.projekt.anwesenheit.periode');

    /*   Gerät */
    Route::get('/ressourcen/geraet', [GeraetController::class, 'index'])->name('geraet.index');
    Route::get('/ressourcen/geraet-edit/{id}', [GeraetController::class, 'edit'])->name('geraet.edit');
    Route::post('/ressourcen/geraet-destroy', [GeraetController::class, 'destroy'])->name('geraet.delete');
    Route::post('/ressourcen/geraet-store', [GeraetController::class, 'store'])->name('geraet.store');
    Route::post('/ressourcen/geraet-update/{id}', [GeraetController::class, 'update'])->name('geraet.update');
    Route::post('/import/geraet', [GeraetController::class, 'import'])->name('geraet.import');
    Route::get('/get-geraet-id', [GeraetController::class, 'getGeraeteID'])->name('getGeraeteID');
    Route::get('/ausleihende', [GeraetController::class, 'indexAusleihende'])->name('geraet.index.ausleihende')->middleware(['check.permission:index-ausleihende'])->middleware('notifications');

    /*   Gerät Ausgabe */
    Route::get('/ressourcen/geraetausgabe', [GeraetausgabeController::class,'index'])->name('geraet.ausgabe.index');
    Route::post('/gressourcen/eraetausgabe', [GeraetausgabeController::class,'store'])->name('geraet.ausgabe.store');
    Route::delete('/ressourcen/geraetausgabe/{id}', [GeraetausgabeController::class, 'destroy'])->name('geraetausgabe.destroy');
    Route::get('/ressourcen/geraetausgabe-view/{id}', [GeraetausgabeController::class, 'view'])->name('ausgabe.view');
    Route::get('/ressourcen/geraetausgabe-excel/{id}', [GeraetausgabeController::class, 'exportExcel'])->name('geraet.ausgabe.export.excel');
    Route::post('/ressourcen/geraetausgabe-store-add', [GeraetausgabeController::class, 'storeAdd'])->name('geraet.ausgabe.store.add');

    /*   Gerät Rückgabe */
    Route::get('/ressourcen/geraet/rueckgabe', [GeraetrueckgabeController::class,'index'])->name('geraet.rueckgabe.index');
    Route::post('/ressourcen/geraet/rueckgabe', [GeraetrueckgabeController::class,'store'])->name('geraet.rueckgabe.store');
    Route::delete('/ressourcen/geraetrueckgabe/{id}', [GeraetrueckgabeController::class, 'destroy'])->name('geraetrueckgabe.destroy');
    Route::get('/ressourcen/geraetrueckgabe-view/{id}', [GeraetrueckgabeController::class, 'view'])->name('rueckgabe.view');
    Route::get('/ressourcen/geraetrueckgabe-excel/{id}', [GeraetrueckgabeController::class, 'exportExcel'])->name('geraet.rueckgabe.export.excel');
    Route::post('/ressourcen/geraetrueckgabe-store-add', [GeraetrueckgabeController::class, 'storeAdd'])->name('geraet.rueckgabe.store.add');
    Route::get('/ressourcen/geraet/rueckgabe/{id}/geraete', [GeraetrueckgabeController::class, 'geraete'])->name('geraet.rueckgabe.geraete');

    /*   Bestellungen // Materialanforderung */
    Route::get('/Bestellungen', [MaterialanforderungController::class, 'index'])->name('materialanforderung.index');
    Route::get('/Materialanforderung/{id}', [MaterialanforderungController::class, 'show'])->name('materialanforderung.show');
    Route::get('/Bestellungen/create', [MaterialanforderungController::class, 'create'])->name('materialanforderung.create');
    Route::post('/Bestellungen/senden', [MaterialanforderungController::class,'store'])->name('materialanforderung.store');
    Route::put('/Bestellungen/update', [MaterialanforderungController::class,'update'])->name('materialanforderung.update');


    Route::post('/materialanforderung/sachlich/{id}/genehmigen', [MaterialanforderungController::class, 'genehmigenSachlich'])->name('materialanforderung.sachlich.genehmigen');
    Route::get('/materialanforderung/{id}/{status}/genehmigen', [MaterialanforderungController::class, 'genehmigen'])->name('materialanforderung.genehmigen');




    Route::post('export-anwesenheitsliste-pobo/preview', [ProjektBopController::class, 'anwesenheitslistePOBOPreviewBIBB'])->name('anwesenheitsliste.POBO.bibb.preview');
    Route::post('export-anwesenheitsliste-pobo/draft', [ProjektBopController::class, 'anwesenheitslistePOBODraftShowBIBB'])->name('anwesenheitsliste.POBO.bibb.draft.show');
    Route::put('export-anwesenheitsliste-pobo/draft', [ProjektBopController::class, 'anwesenheitslistePOBODraftStoreBIBB'])->name('anwesenheitsliste.POBO.bibb.draft.store');
    Route::delete('export-anwesenheitsliste-pobo/draft', [ProjektBopController::class, 'anwesenheitslistePOBODraftDestroyBIBB'])->name('anwesenheitsliste.POBO.bibb.draft.destroy');
    Route::post('export-anwesenheitsliste-pobo/archive-folder', [ProjektBopController::class, 'anwesenheitslistePOBOArchiveFolderBIBB'])->name('anwesenheitsliste.POBO.bibb.archive.folder');
    Route::post('export-anwesenheitsliste-pobo/pdf-folder', [ProjektBopController::class, 'anwesenheitslistePOBOSignedPdfStoreBIBB'])->name('anwesenheitsliste.POBO.bibb.pdf.store');
    Route::post('export-anwesenheitsliste-pobo', [ProjektBopController::class, 'anwesenheitslistePOBOExportWordBIBB'])->name('anwesenheitsliste.POBO.bibb.export.word');
    Route::post('export-anwesenheitsliste/pa/preview', [ProjektBopController::class, 'anwesenheitslistePAPreviewDigital'])->name('anwesenheitsliste.PA.digital.preview');
    Route::post('export-anwesenheitsliste/pa/draft', [ProjektBopController::class, 'anwesenheitslistePADraftShow'])->name('anwesenheitsliste.PA.digital.draft.show');
    Route::put('export-anwesenheitsliste/pa/draft', [ProjektBopController::class, 'anwesenheitslistePADraftStore'])->name('anwesenheitsliste.PA.digital.draft.store');
    Route::delete('export-anwesenheitsliste/pa/draft', [ProjektBopController::class, 'anwesenheitslistePADraftDestroy'])->name('anwesenheitsliste.PA.digital.draft.destroy');
    Route::post('export-anwesenheitsliste/pa/archive-folder', [ProjektBopController::class, 'anwesenheitslistePAArchiveFolder'])->name('anwesenheitsliste.PA.digital.archive.folder');
    Route::post('export-anwesenheitsliste/pa/pdf-folder', [ProjektBopController::class, 'anwesenheitslistePASignedPdfStore'])->name('anwesenheitsliste.PA.digital.pdf.store');
    Route::post('export-anwesenheitsliste/pa', [ProjektBopController::class, 'anwesenheitslistePAexportWord'])->name('anwesenheitsliste.PA.export.word');
    Route::get('/export-anwesenheitsliste-pobo/tag1/{partnerID}/{schuljahr}/{teil}/{klasse?}', [ProjektBopController::class, 'anwesenheitslistePOBOTag1'])->name('anwesenheitsliste.BoTag1.export');
    Route::get('/export/hausordnung/{partnerId}/{schuljahr}/{teil}/{sortBy}/{termin}', [ProjektBopController::class, 'hausordnungExportPdf'])->name('hausordnung.export.schule.pdf');


    Route::get('/bereichsauswahl/{partnerId}/{schuljahr}/{teil}', [ProjektBopController::class, 'bereichsauswahl'])->name('bereichsauswahl.index');
    Route::post('/bereichsauswahl/einstellung', [ProjektBopController::class, 'bereichsauswahlSettingUpdate'])->name('bereichsauswahl.setting.update');
    Route::post('/bereichwahl-update', [ProjektBopController::class, 'waehlen'])->name('bereichsauswahl.bop.radio.update');

    Route::get('/export/auswertungsbogen/pa/pdf/{partnerId}/{schuljahr}/{teil}', [ProjektBopController::class, 'generatePdfauswertungsbogenPASchule'])->name('export.auswertungsbogenPA.schule.pdf');
    Route::get('/export/auswertungsbogen/pa/roland/pdf/{partnerId}/{schuljahr}/{teil}', [ProjektBopController::class, 'generatePdfAuswertungsbogenPaRolandSchule'])->name('export.auswertungsbogenPA.roland.schule.pdf');
    Route::get('/export/elterneinverstaendniserklaerung/{partnerId}/{schuljahr}/{teil}', [ProjektBopController::class, 'exportElterneinverstaendniserklaerungSchule'])->name('export.elterneinverstaendniserklaerung.schule');


    //Einteilung Berieche
    Route::get('/einteilung/{partnerId}/{schuljahr}/{teil}', [EinteilungParameterController::class, 'index'])->name('einteilung.show');
    Route::post('/einteilung/update', [EinteilungParameterController::class, 'update'])->name('einteilung.update');
    Route::post('/einteilung/create', [EinteilungParameterController::class, 'createManual'])->name('einteilung.create');
    Route::post('/einteilung/parameter', [EinteilungParameterController::class, 'updateParameter'])->name('einteilung.parameter.update');
    Route::post('/einteilung/runden-tauschen', [EinteilungParameterController::class, 'switchRunden'])->name('einteilung.runden.switch');
    Route::post('/einteilung/einteilen', [EinteilungParameterController::class, 'einteilen'])->name('einteilung.store');
    Route::post('/einteilung/destroy-context', [EinteilungParameterController::class, 'destroyContext'])->name('einteilung.destroy');
    Route::post('/einteilung/gruppen-generieren', [EinteilungParameterController::class, 'gruppenGenerieren'])->name('gruppen.generieren');
    Route::post('/einteilung/export-excel', [EinteilungParameterController::class, 'exportExcel'])->name('einteilung.export.excel');


    //zu bearbeiten
Route::get('/anwesenheitsdaten/{schulId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'anwesenheitsdaten'])->name('index-anpassung-anwesenheitsdaten');
Route::post('/anwesenheitsdaten/{schulId}/{schuljahr}/{teil}/export', [BopLegacyFunctionController::class, 'anwesenheitsdatenExport'])->name('export.anwesenheitsdaten.schule.excel');
Route::get('/teilnehmerliste/excel/{schuleId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'teilnehmerliste'])->name('export.teilnehmerliste.schule.excel');
Route::get('/teilnehmerccliste/excel/{schuleId}/{schuljahr}/{teil}', [MaterialanforderungController::class, 'index'])->name('teilnehmer.liste.schule');
Route::get('/alleTeilnehmer/folder/create/{idSchule}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'createFolderAll'])->name('alleTeilnehmer.folder.create');
Route::get('/anwesenheitsliste/vorbereitung/bo/{schuleId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'anwesenheitslisteVorbereitung'])->name('anwesenheitslisteVorBOTage');
Route::get('/export/anwesenheitsliste/rechnung/{idSchule}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'anwesenheitslisteRechnung'])->name('export.anwesenheitsliste.rechnung');
Route::get('/export/zertifikat/pobo/{idSchule}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'zertifikatPobo'])->name('export.zertifikat.schule.pobo');
Route::get('/export/zertifikat/pobo/pdf/{schuleId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'zertifikatPoboPdf'])->name('export.zertifikat.schule.pobo.pdf');
Route::get('/export/auswertung/pobo/{schulId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'auswertungPobo'])->name('export.auswertungBO.schule.pdf');
Route::get('/export/auswertung/pobo/tofolder/{schulId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'auswertungPoboToFolder'])->name('export.auswertungBO.schule.pdf.tofolder');
Route::get('/export/auswertung/pa/tofolder/{schulId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'auswertungPaToFolder'])->name('export.auswertungPA.schule.pdf.tofolder');
Route::get('/export/auswertung/pobo/runde/{schuleId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'auswertungPoboRunde'])->name('auswertungPoboModal');



});
