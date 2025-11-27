<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BriefController;
use App\Http\Controllers\NotizController;
use App\Http\Controllers\BaenkeController;
use App\Http\Controllers\GruppeController;
use App\Http\Controllers\SchuleController;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\BereichController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProjektController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\StandortController;
use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\DashbaordController;
use App\Http\Controllers\FahrzeugeController;
use App\Http\Controllers\AbschlusseController;
use App\Http\Controllers\ExportWordController;
use App\Http\Controllers\FahrtartenController;
use App\Http\Controllers\TeilnehmerController;
use App\Http\Controllers\AnwesenheitController;
use App\Http\Controllers\DienstwagenController;
use App\Http\Controllers\ExportExcelController;
use App\Http\Controllers\BerechtigungController;
use App\Http\Controllers\KostenstelleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RaumlichkeitenController;
use App\Http\Controllers\TransportartenController;
use App\Http\Controllers\DienstwagenkostenController;
use App\Http\Controllers\FahrtkostensaetzeController;
use App\Http\Controllers\DienstwagenreportsController;
use App\Http\Controllers\DienstwagenwartungController;
use App\Http\Controllers\ProjektHasPersonenController;
use App\Http\Controllers\GruppeHasTeilnehmerController;
use App\Http\Controllers\FahrtkostenAbrechnenController;
use App\Http\Controllers\ProjektHasTeilnehmerController;
use App\Http\Controllers\DienstwagenfahrtenbuchController;
use App\Http\Controllers\ProjektHasTeilnehmerLuvController;


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
//Route::middleware(['auth', 'verified', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {

Route::middleware(['auth', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {




    Route::get('/dashboard', [DashbaordController::class, 'dashboard'])->name('dashboard');


    Route::get('/organisation', function () {
        return Inertia::render('Dashboards/Organisation');
    })->name('organisation.index');

    Route::get('/ressourcen', function () {
        return Inertia::render('Dashboards/Ressourcen');
    })->name('ressourcen.index');

    Route::get('/finanzen', function () {
        return Inertia::render('Dashboards/Finanzen');
    })->name('finanzen.index');


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
    Route::get('/berechtigung/{id?}', [BerechtigungController::class, 'index'])->name('berechtigung.index');
    Route::post('/berechtigungZuweisen', [BerechtigungController::class, 'berechtigungZuweisen'])->name('berechtigung.zuweisen');
    Route::delete('/berechtigung/{id}', [BerechtigungController::class, 'destroy'])->name('rolle.destroy');


    //Benutzer
    Route::get('/benutzer', [UserController::class, 'index'])->name('user.index');
    Route::get('/benutzer/anlegen', function () { return Inertia::render('User/CreateUser'); })->name('user.create');
    Route::post('/benutzer/anlegen', [UserController::class, 'store'])->name('user.store');
    Route::delete('/benutzer/entfernen/{id}', [UserController::class, 'destroy'])->name('user.destroy');
    Route::post('/benutzer/projekt/switch', [UserController::class, 'switch'])->name('projekt.switch');
    Route::get('/benutzer/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
    Route::put('/benutzer/update/{user}', [UserController::class, 'update'])->name('user.update');

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
    Route::post('/projekt/anlegen', [ProjektController::class, 'store'])->name('projekt.store');
    Route::put('/projekt/{id}', [ProjektController::class, 'update'])->name('projekt.update');
    Route::delete('/projekt/{id}', [ProjektController::class, 'destroy'])->name('projekt.destroy');

    //Gruppe
    Route::get('/gruppe', [GruppeController::class, 'index'])->name('gruppe.index');
    Route::put('/gruppe/{id}', [GruppeController::class, 'update'])->name('gruppe.update');
    Route::delete('/gruppe/{id}', [GruppeController::class, 'destroy'])->name('gruppe.destroy');
    Route::post('/gruppe/anlegen', [GruppeController::class, 'store'])->name('gruppe.store');

    //GruppeHasTeilnehmer
    Route::get('/gruppehasteilnehmer/{id}', [GruppeHasTeilnehmerController::class, 'show'])->name('gruppeHasTeilnehmer.show');

    Route::post('/gruppehasteilnehmer/anlegen', [GruppeHasTeilnehmerController::class, 'store'])->name('gruppeHasTeilnehmer.store');

    Route::delete('/gruppehasteilnehmer/entfernen/{id}', [GruppeHasTeilnehmerController::class, 'destroy'])->name('gruppeHasPersonen.destroy');

    //Teilnehmer
    Route::get('/teilnehmer', [TeilnehmerController::class, 'index'])->name('teilnehmer.index');

    Route::get('/teilnehmer/{id}', [TeilnehmerController::class, 'indexNachProjekt'])->name('teilnehmer.projekt.index');

    Route::get('/teilnehmer/anlegen', function () { return Inertia::render('Teilnehmer/CreateTeilnehmer'); })->name('teilnehmer.create');
    Route::post('/teilnehmer/anlegen', [TeilnehmerController::class, 'store'])->name('teilnehmer.store');
    Route::delete('/teilnehmer/entfernen/{id}', [TeilnehmerController::class, 'destroy'])->name('teilnehmer.destroy');
    Route::get('/teilnehmer/bearbeiten/{id}', [TeilnehmerController::class, 'show'])->name('teilnehmer.edit');
    Route::patch('/teilnehmer/update/{id}', [TeilnehmerController::class, 'update'])->name('teilnehmer.update');
    Route::patch('/teilnehmer/{person}/sozialdaten', [TeilnehmerController::class, 'updateSozialdaten'])->name('person.sozialdaten.update');

    // Personen Has Abschluss
    Route::post('/teilnehmer/abschluss/anlegen', [AbschlusseController::class, 'store'])->name('abschluss.store');
    Route::delete('/teilnehmer/abschluss/entfernen/{id}', [AbschlusseController::class, 'destroy'])->name('abschluss.destroy');




    //Räumlichkeiten
    Route::get('/ressourcen/standort/raeumlichkeiten/', [RaumlichkeitenController::class, 'index'])->name('raeumlichkeiten.index');
    Route::post('/ressourcen/standort/raeumlichkeiten/anlegen', [RaumlichkeitenController::class, 'store'])->name('raeumlichkeiten.store');
    Route::put('/ressourcen/standort/raeumlichkeiten/update/{id}', [RaumlichkeitenController::class, 'update'])->name('raeumlichkeiten.update');
    Route::delete('/ressourcen/standort/raeumlichkeiten/entfernen/{id}', [RaumlichkeitenController::class, 'destroy'])->name('raeumlichkeiten.destroy');



    //Anwesenheiten

    Route::post('/anwesenheit/speichern', [AnwesenheitController::class, 'store'])->name('anwesenheit.store');

    Route::delete('/anwesenheit/entfernen/{id}', [AnwesenheitController::class, 'destroy'])->name('anwesenheit.destroy');

    Route::post('/anwesenheit/update', [AnwesenheitController::class, 'update'])->name('anwesenheit.update');

    //Kontakte
    Route::delete('/teilnehmer/kontakt/entfernen/{id}', [KontaktController::class, 'destroy'])->name('kontakt.destroy');
    Route::post('/teilnehmer/kontakt/anlegen', [KontaktController::class, 'store'])->name('kontakt.store');



    //Adresse
    Route::post('/teilnehmer/adresse/anlegen', [AdresseController::class, 'store'])->name('adresse.store');
    Route::delete('/teilnehmer/adresse/entfernen/{id}', [AdresseController::class, 'destroy'])->name('adresse.destroy');


    //ProjektHasTeilnehmer
    Route::post('/teilnehmer/projekt/anlegen', [ProjektHasTeilnehmerController::class, 'store'])->name('projekthasteilnehmer.store');
    Route::put('/teilnehmer/projekt/edit', [ProjektHasTeilnehmerController::class, 'update'])->name('projekthasteilnehmer.update');

    //ProjektHasTeilnehmerLuv
    Route::post('/teilnehmer/projekt/luv/anlegen', [ProjektHasTeilnehmerLuvController::class, 'store'])->name('projekthasteilnehmer.luv.store');
    Route::put('/teilnehmer/projekt/luv/edit', [ProjektHasTeilnehmerLuvController::class, 'update'])->name('projekthasteilnehmer.luv.update');
    //ProjektHasPersonen
    Route::post('/personen/projekt/zuweisen', [ProjektHasPersonenController::class, 'store'])->name('projekthaspersonen.store');
    Route::delete('/personen/projekt/entfernen/{id}', [ProjektHasPersonenController::class, 'destroy'])->name('projekthaspersonen.destroy');


    //Teilnehmer Bank
    Route::post('/teilnehmer/bank/anlegen', [BaenkeController::class, 'store'])->name('bank.store');
    Route::delete('/teilnehmer/bank/entfernen/{id}', [BaenkeController::class, 'destroy'])->name('bank.destroy');

    //Partner
    Route::get('/organisation/partner', [PartnerController::class, 'index'])->name('partner.index');
    Route::post('/organisation/partner/anlegen', [PartnerController::class, 'store'])->name('partner.store');
    Route::delete('/organisation/partner/entfernen/{id}', [PartnerController::class, 'destroy'])->name('partner.destroy');
    Route::put('/organisation/partner/edit/{id}', [PartnerController::class, 'update'])->name('partner.update');

    Route::get('/organisation/partner/ajax/fresh', [PartnerController::class, 'indexAjaxFresh'])->name('partner.indexAjaxFresh');





    //Kostenstelle
    Route::get('/kostenstelle', [KostenstelleController::class, 'index'])->name('kostenstelle.index');


    Route::get('/design/responsive', function () {
        return Inertia::render('Design/Responsive');
    })->name('responsive');



    //Notification

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
    ->name('notifications.readAll');

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


});







