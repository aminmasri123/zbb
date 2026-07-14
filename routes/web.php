<?php

use App\Http\Controllers\AbschlusseController;
use App\Http\Controllers\AccountDeletionRequestController;
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
use App\Http\Controllers\DienstwagenBuchungController;
use App\Http\Controllers\DienstwagenfahrtenbuchController;
use App\Http\Controllers\DienstwagenkostenController;
use App\Http\Controllers\DienstwagenMeldungController;
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
use App\Http\Controllers\ItServiceController;
use App\Http\Controllers\IntakeChecklistController;
use App\Http\Controllers\KlassenbuchController;
use App\Http\Controllers\KontaktController;
use App\Http\Controllers\KostenstelleController;
use App\Http\Controllers\LagerController;
use App\Http\Controllers\MaterialanforderungController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationRuleController;
use App\Http\Controllers\NotizController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ParticipationTaskController;
use App\Http\Controllers\ParticipantPortalController;
use App\Http\Controllers\PortalJobController;
use App\Http\Controllers\ParticipationApplicationController;
use App\Http\Controllers\ProjectCourseController;
use App\Http\Controllers\PortalLearningController;
use App\Http\Controllers\PortalSelfServiceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\PortalDocumentController;
use App\Http\Controllers\ParticipantDocumentController;
use App\Http\Controllers\ParticipantMessageController;
use App\Http\Controllers\ProjectConsentController;
use App\Http\Controllers\PortalConsentController;
use App\Http\Controllers\ParticipantDataRequestController;
use App\Http\Controllers\ParticipantJobRecommendationController;
use App\Http\Controllers\ParticipantApplicationPackageController;
use App\Http\Controllers\ProjectCourseContentController;
use App\Http\Controllers\PortalLearningContentController;
use App\Http\Controllers\ProjectCourseQuizController;
use App\Http\Controllers\PortalCourseQuizController;
use App\Http\Controllers\ParticipantContactController;
use App\Http\Controllers\ParticipantCvController;
use App\Http\Controllers\ParticipantCareerStudioController;
use App\Http\Controllers\ParticipantApplicationDispatchController;
use App\Http\Controllers\ProjectCourseSessionController;
use App\Http\Controllers\ParticipationCompletionController;
use App\Http\Controllers\ParticipantNotificationPreferenceController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PersonenHasBildungsmassnahmenController;
use App\Http\Controllers\PotenzialanalyseController;
use App\Http\Controllers\ProjektBopController;
use App\Http\Controllers\ProjektController;
use App\Http\Controllers\ProjektHasPersonenController;
use App\Http\Controllers\ProjektHasTeilnehmerController;
use App\Http\Controllers\ProjektHasTeilnehmerLuvController;
use App\Http\Controllers\RaumlichkeitenController;
use App\Http\Controllers\ModuleSettingsController;
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

Route::get('/portal', [ParticipantPortalController::class, 'welcome'])
    ->middleware('module:participant_portal')
    ->name('participant-portal.welcome');
Route::middleware(['module:participant_portal', 'throttle:10,1'])->group(function () {
    Route::get('/portal/anmelden', [ParticipantPortalController::class, 'loginForm'])->name('participant-portal.login');
    Route::post('/portal/anmelden', [ParticipantPortalController::class, 'login'])->name('participant-portal.login.store');
    Route::get('/portal/einladung/{token}', [ParticipantPortalController::class, 'invitation'])->name('participant-portal.invitation.show');
    Route::post('/portal/einladung/{token}', [ParticipantPortalController::class, 'acceptInvitation'])->name('participant-portal.invitation.accept');
});
Route::middleware(['module:participant_portal', 'auth', 'participantPortalUser'])->group(function () {
    Route::get('/portal/dashboard', [ParticipantPortalController::class, 'dashboard'])->name('participant-portal.dashboard');
    Route::post('/portal/farbpalette', function () {
        $data = request()->validate(['theme' => ['required', 'string', 'in:air,dark,womanly,champion,sprint,arena,pulse,trail,bazaar,vital']]);
        request()->user()->forceFill(['theme' => $data['theme']])->save();

        return response()->json(['success' => true, 'theme' => $data['theme']]);
    })->name('participant-portal.theme.update');
    Route::get('/portal/benachrichtigungen/einstellungen', [ParticipantNotificationPreferenceController::class, 'index'])->name('participant-portal.notification-preferences.index');
    Route::put('/portal/benachrichtigungen/einstellungen', [ParticipantNotificationPreferenceController::class, 'update'])->name('participant-portal.notification-preferences.update');
    Route::put('/portal/profil', [ParticipantPortalController::class, 'updateProfile'])->name('participant-portal.profile.update');
    Route::post('/portal/abmelden', [ParticipantPortalController::class, 'logout'])->name('participant-portal.logout');
    Route::get('/portal/jobs', [PortalJobController::class, 'index'])->name('participant-portal.jobs.index');
    Route::get('/portal/jobs/suche', [PortalJobController::class, 'search'])->name('participant-portal.jobs.search')->middleware('throttle:30,1');
    Route::post('/portal/jobs/merkliste', [PortalJobController::class, 'storeBookmark'])->name('participant-portal.jobs.bookmarks.store');
    Route::delete('/portal/jobs/merkliste/{bookmark}', [PortalJobController::class, 'destroyBookmark'])->name('participant-portal.jobs.bookmarks.destroy');
    Route::post('/portal/bewerbungen', [PortalJobController::class, 'storeApplication'])->name('participant-portal.applications.store');
    Route::put('/portal/bewerbungen/{application}', [PortalJobController::class, 'updateApplication'])->name('participant-portal.applications.update');
    Route::put('/portal/bewerbungen/{application}/dokumente', [ParticipantApplicationPackageController::class, 'portalSync'])->name('participant-portal.applications.documents.sync');
    Route::post('/portal/bewerbungen/{application}/freigabe', [ParticipantApplicationPackageController::class, 'portalApprove'])->name('participant-portal.applications.package.approve');
    Route::put('/portal/stellenempfehlungen/{recommendation}/angesehen', [ParticipantJobRecommendationController::class, 'viewed'])->name('participant-portal.recommendations.viewed');
    Route::put('/portal/stellenempfehlungen/{recommendation}/verwerfen', [ParticipantJobRecommendationController::class, 'dismiss'])->name('participant-portal.recommendations.dismiss');
    Route::post('/portal/stellenempfehlungen/{recommendation}/bewerbung', [ParticipantJobRecommendationController::class, 'convert'])->name('participant-portal.recommendations.convert');
    Route::get('/portal/kurse', [PortalLearningController::class, 'index'])->name('participant-portal.learning.index');
    Route::get('/portal/kurse/termine', [PortalLearningContentController::class, 'sessions'])->name('participant-portal.learning.sessions.index');
    Route::post('/portal/kurse/{course}/einschreiben', [PortalLearningController::class, 'enroll'])->name('participant-portal.learning.enroll');
    Route::put('/portal/kurse/einschreibung/{enrollment}/lektion/{lesson}', [PortalLearningController::class, 'updateProgress'])->name('participant-portal.learning.progress.update');
    Route::get('/portal/kurse/material/{material}/download', [PortalLearningContentController::class, 'downloadMaterial'])->name('participant-portal.learning.materials.download');
    Route::post('/portal/kurse/aufgaben/{assignment}/abgabe', [PortalLearningContentController::class, 'submit'])->name('participant-portal.learning.assignments.submit');
    Route::get('/portal/kurse/abgaben/{submission}/download', [PortalLearningContentController::class, 'downloadSubmission'])->name('participant-portal.learning.submissions.download');
    Route::post('/portal/kurse/quiz/{quiz}/versuch', [PortalCourseQuizController::class, 'submit'])->name('participant-portal.learning.quizzes.submit');
    Route::get('/portal/kurse/quiz', [PortalCourseQuizController::class, 'index'])->name('participant-portal.learning.quizzes.index');
    Route::get('/portal/meine-daten', [PortalSelfServiceController::class, 'index'])->name('participant-portal.self-service.index');
    Route::post('/portal/anwesenheit/{attendance}/korrektur', [PortalSelfServiceController::class, 'requestCorrection'])->name('participant-portal.attendance.corrections.store');
    Route::get('/portal/dokumente', [PortalDocumentController::class, 'index'])->name('participant-portal.documents.index');
    Route::post('/portal/dokumente', [PortalDocumentController::class, 'store'])->name('participant-portal.documents.store');
    Route::get('/portal/dokumente/{document}/download', [PortalDocumentController::class, 'download'])->name('participant-portal.documents.download');
    Route::delete('/portal/dokumente/{document}', [PortalDocumentController::class, 'destroy'])->name('participant-portal.documents.destroy');
    Route::get('/portal/nachrichten', [ParticipantMessageController::class, 'portalIndex'])->name('participant-portal.messages.index');
    Route::post('/portal/nachrichten', [ParticipantMessageController::class, 'portalStore'])->name('participant-portal.messages.store');
    Route::put('/portal/nachrichten/teilnahme/{participation}/gelesen', [ParticipantMessageController::class, 'portalRead'])->name('participant-portal.messages.read');
    Route::get('/portal/einwilligungen', [PortalConsentController::class, 'index'])->name('participant-portal.consents.index');
    Route::post('/portal/einwilligungen/{definition}', [PortalConsentController::class, 'act'])->name('participant-portal.consents.act');
    Route::get('/portal/datenauskunft', [ParticipantDataRequestController::class, 'portalIndex'])->name('participant-portal.data-requests.index');
    Route::post('/portal/datenauskunft', [ParticipantDataRequestController::class, 'portalStore'])->name('participant-portal.data-requests.store');
    Route::get('/portal/datenauskunft/{dataRequest}/download', [ParticipantDataRequestController::class, 'download'])->name('participant-portal.data-requests.download');
    Route::get('/portal/kontakt', [ParticipantContactController::class, 'index'])->name('participant-portal.contact.index');
    Route::post('/portal/kontakt/e-mail', [ParticipantContactController::class, 'requestEmail'])->name('participant-portal.contact.email.request')->middleware('throttle:5,1');
    Route::get('/portal/kontakt/e-mail/bestaetigen/{token}', [ParticipantContactController::class, 'confirm'])->name('participant-portal.contact.email.confirm')->middleware('throttle:10,1');
    Route::delete('/portal/kontakt/anfragen/{change}', [ParticipantContactController::class, 'cancel'])->name('participant-portal.contact.cancel');
    Route::get('/portal/lebenslauf', [ParticipantCvController::class, 'index'])->name('participant-portal.resume.index');
    Route::get('/portal/bewerbungsstudio', [ParticipantCareerStudioController::class, 'index'])->name('participant-portal.career-studio.index');
    Route::post('/portal/bewerbungsstudio/dokumente', [ParticipantCareerStudioController::class, 'store'])->name('participant-portal.career-studio.store');
    Route::put('/portal/bewerbungsstudio/dokumente/{document}', [ParticipantCareerStudioController::class, 'update'])->name('participant-portal.career-studio.update');
    Route::post('/portal/bewerbungsstudio/dokumente/{document}/kopieren', [ParticipantCareerStudioController::class, 'duplicate'])->name('participant-portal.career-studio.duplicate');
    Route::delete('/portal/bewerbungsstudio/dokumente/{document}', [ParticipantCareerStudioController::class, 'destroy'])->name('participant-portal.career-studio.destroy');
    Route::get('/portal/bewerbungsstudio/dokumente/{document}/vorschau', [ParticipantCareerStudioController::class, 'preview'])->name('participant-portal.career-studio.preview');
    Route::get('/portal/bewerbungsstudio/dokumente/{document}/download', [ParticipantCareerStudioController::class, 'download'])->name('participant-portal.career-studio.download');
    Route::put('/portal/bewerbungen/{application}/studio-dokumente', [ParticipantApplicationDispatchController::class, 'sync'])->name('participant-portal.applications.career-documents.sync');
    Route::post('/portal/bewerbungen/{application}/notizen', [ParticipantApplicationDispatchController::class, 'note'])->name('participant-portal.applications.notes.store');
    Route::post('/portal/bewerbungen/{application}/versenden', [ParticipantApplicationDispatchController::class, 'send'])->name('participant-portal.applications.send');
    Route::post('/portal/lebenslauf/eintraege', [ParticipantCvController::class, 'store'])->name('participant-portal.resume.entries.store');
    Route::put('/portal/lebenslauf/eintraege/{entry}', [ParticipantCvController::class, 'update'])->name('participant-portal.resume.entries.update');
    Route::delete('/portal/lebenslauf/eintraege/{entry}', [ParticipantCvController::class, 'destroy'])->name('participant-portal.resume.entries.destroy');
    Route::post('/portal/lebenslauf/versionen', [ParticipantCvController::class, 'createVersion'])->name('participant-portal.resume.versions.store');
    Route::get('/portal/lebenslauf/versionen/{version}/download', [ParticipantCvController::class, 'download'])->name('participant-portal.resume.versions.download');
    Route::get('/portal/lebenslauf/versionen/{version}/druck', [ParticipantCvController::class, 'print'])->name('participant-portal.resume.versions.print');
});




Route::post('/set-locale', function () {
    request()->validate(['locale' => 'required|string']);
    session(['locale' => request('locale')]);
    return response()->json(['success' => true]);
});




// Geschützte Routen
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/bereichsauswahl/zugang/{token}', [ProjektBopController::class, 'bereichsauswahlSelfShow'])->name('bereichsauswahl.self.show');
    Route::get('/bereichsauswahl/zugang/{token}/danke', [ProjektBopController::class, 'bereichsauswahlSelfThanks'])->name('bereichsauswahl.self.thanks');
});

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/bereichsauswahl/zugang/{token}/code', [ProjektBopController::class, 'bereichsauswahlSelfVerify'])->name('bereichsauswahl.self.verify');
    Route::post('/bereichsauswahl/zugang/{token}', [ProjektBopController::class, 'bereichsauswahlSelfStore'])->name('bereichsauswahl.self.store');
});

//Route::middleware(['auth', 'verified', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {

Route::middleware(['auth', 'injectUserPermissions', 'injectUserProjekte', 'routePermission', 'configuredNotifications'])->group(function() {

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

    Route::post('/konto/loeschantrag', [AccountDeletionRequestController::class, 'store'])
        ->name('account-deletion-requests.store');

    Route::get('/dashboard', [DashbaordController::class, 'dashboard'])->name('dashboard');
    Route::put('/dashboard/einstellungen', [DashbaordController::class, 'updatePreferences'])->name('dashboard.preferences.update');
    Route::get('/fw/{id}', [DienstwagenfahrtenbuchController::class, 'scan'])
        ->name('dienstwagen.fahrtenbuch.scan')
        ->can('dienstwagen.fahrtenbuch.index');

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
    Route::post('/berechtigungZuweisen', [BerechtigungController::class, 'berechtigungZuweisen'])->name('berechtigung.zuweisen')->middleware('canAnyPermission:berechtigung.zuweisen,berechtigung.update');
    Route::post('/berechtigungKategorieZuweisen', [BerechtigungController::class, 'berechtigungKategorieZuweisen'])->name('berechtigung.kategorie.zuweisen')->middleware('canAnyPermission:berechtigung.zuweisen,berechtigung.update');
    Route::put('/rolle/{role}/datenzugriff', [RoleDataAccessController::class, 'update'])->name('rolle.data-access.update')->middleware('canAnyPermission:rolle.data-access.update,berechtigung.update');
    Route::get('/einstellung/benachrichtigungen', [NotificationRuleController::class, 'index'])->name('notification-rules.index')->middleware('canAnyPermission:notification-rules.index,notification-rules.update,berechtigung.update');
    Route::post('/einstellung/benachrichtigungen', [NotificationRuleController::class, 'store'])->name('notification-rules.store')->middleware('canAnyPermission:notification-rules.store,notification-rules.update,berechtigung.update');
    Route::put('/einstellung/benachrichtigungen/{notificationRule}', [NotificationRuleController::class, 'update'])->name('notification-rules.update')->middleware('canAnyPermission:notification-rules.update,berechtigung.update');
    Route::delete('/einstellung/benachrichtigungen/{notificationRule}', [NotificationRuleController::class, 'destroy'])->name('notification-rules.destroy')->middleware('canAnyPermission:notification-rules.destroy,notification-rules.update,berechtigung.update');

    Route::delete('/rolle/loeschen/{id}', [RolleController::class, 'destroy'])->name('rolle.destroy')->middleware('canAnyPermission:rolle.destroy,berechtigung.update');
    Route::post('/rolle/anlegen', [RolleController::class, 'store'])->name('rolle.store')->middleware('canAnyPermission:rolle.store,berechtigung.store,berechtigung.update');
    Route::get('/einstellung/module', [ModuleSettingsController::class, 'index'])->name('module-settings.index')->can('berechtigung.update');
    Route::put('/einstellung/module/{module}', [ModuleSettingsController::class, 'update'])->name('module-settings.update')->can('berechtigung.update');

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
    Route::put('/projekt/{projekt}/funktionen', [ProjektController::class, 'updateFeatures'])->name('projekt.features.update')->can('projekt.update');
    Route::put('/projekt/{projekt}/regeln', [ProjektController::class, 'updateRules'])->name('projekt.rules.update')->can('projekt.update');
    Route::put('/projekt/{projekt}/aufnahmecheckliste', [IntakeChecklistController::class, 'updateDefinition'])->name('projekt.intake-checklist.update')->can('projekt.update');
    Route::put('/projekt/{projekt}/abschlusscheckliste', [ParticipationCompletionController::class, 'updateDefinition'])->name('projekt.completion-checklist.update')->middleware('projectFeature:completion_management')->can('projekt.update');
    Route::put('/projekt/{projekt}/portal-funktionen', [ProjektController::class, 'updatePortalFeatures'])->name('projekt.portal-features.update')->can('projekt.update');
    Route::get('/projekt/{projekt}/einwilligungen', [ProjectConsentController::class, 'index'])->name('projekt.consents.index')->middleware('module:participant_portal')->can('projekt.update');
    Route::post('/projekt/{projekt}/einwilligungen', [ProjectConsentController::class, 'store'])->name('projekt.consents.store')->middleware('module:participant_portal')->can('projekt.update');
    Route::post('/projekt/einwilligungen/{definition}/version', [ProjectConsentController::class, 'revise'])->name('projekt.consents.revise')->middleware('module:participant_portal')->can('projekt.update');
    Route::put('/projekt/einwilligungen/{definition}/status', [ProjectConsentController::class, 'setActive'])->name('projekt.consents.active')->middleware('module:participant_portal')->can('projekt.update');
    Route::middleware('module:participant_portal')->group(function () {
        Route::get('/projekt/{projekt}/kurse', [ProjectCourseController::class, 'index'])->name('projekt.courses.index')->can('projekt.update');
        Route::post('/projekt/{projekt}/kurse', [ProjectCourseController::class, 'store'])->name('projekt.courses.store')->can('projekt.update');
        Route::put('/projekt/kurse/{course}', [ProjectCourseController::class, 'update'])->name('projekt.courses.update')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/lektionen', [ProjectCourseController::class, 'storeLesson'])->name('projekt.courses.lessons.store')->can('projekt.update');
        Route::put('/projekt/kurse/lektionen/{lesson}', [ProjectCourseController::class, 'updateLesson'])->name('projekt.courses.lessons.update')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/einschreibungen', [ProjectCourseController::class, 'enroll'])->name('projekt.courses.enroll')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/materialien', [ProjectCourseContentController::class, 'storeMaterial'])->name('projekt.courses.materials.store')->can('projekt.update');
        Route::put('/projekt/kurse/materialien/{material}', [ProjectCourseContentController::class, 'updateMaterial'])->name('projekt.courses.materials.update')->can('projekt.update');
        Route::get('/projekt/kurse/materialien/{material}/download', [ProjectCourseContentController::class, 'downloadMaterial'])->name('projekt.courses.materials.download')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/aufgaben', [ProjectCourseContentController::class, 'storeAssignment'])->name('projekt.courses.assignments.store')->can('projekt.update');
        Route::put('/projekt/kurse/aufgaben/{assignment}', [ProjectCourseContentController::class, 'updateAssignment'])->name('projekt.courses.assignments.update')->can('projekt.update');
        Route::put('/projekt/kurse/abgaben/{submission}/bewerten', [ProjectCourseContentController::class, 'review'])->name('projekt.courses.submissions.review')->can('projekt.update');
        Route::get('/projekt/kurse/abgaben/{submission}/download', [ProjectCourseContentController::class, 'downloadSubmission'])->name('projekt.courses.submissions.download')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/quiz', [ProjectCourseQuizController::class, 'storeQuiz'])->name('projekt.courses.quizzes.store')->can('projekt.update');
        Route::put('/projekt/kurse/quiz/{quiz}', [ProjectCourseQuizController::class, 'updateQuiz'])->name('projekt.courses.quizzes.update')->can('projekt.update');
        Route::post('/projekt/kurse/quiz/{quiz}/fragen', [ProjectCourseQuizController::class, 'storeQuestion'])->name('projekt.courses.quizzes.questions.store')->can('projekt.update');
        Route::put('/projekt/kurse/quiz/fragen/{question}', [ProjectCourseQuizController::class, 'updateQuestion'])->name('projekt.courses.quizzes.questions.update')->can('projekt.update');
        Route::post('/projekt/kurse/{course}/termine', [ProjectCourseSessionController::class, 'store'])->name('projekt.courses.sessions.store')->can('projekt.update');
        Route::put('/projekt/kurse/termine/{session}', [ProjectCourseSessionController::class, 'update'])->name('projekt.courses.sessions.update')->can('projekt.update');
        Route::put('/projekt/kurse/termine/{session}/teilnahme', [ProjectCourseSessionController::class, 'record'])->name('projekt.courses.sessions.attendance')->can('projekt.update');
    });
    Route::delete('/projekt/{id}', [ProjektController::class, 'destroy'])->name('projekt.destroy');
    Route::middleware('projectFeature:potential_analysis')->group(function () {
        Route::post('/projekt/{projekt}/potenzialanalyse/uebungen', [PotenzialanalyseController::class, 'storeUebung'])->name('potenzialanalyse.projekt.uebungen.store');
        Route::put('/potenzialanalyse/uebungen/{uebung}', [PotenzialanalyseController::class, 'updateUebung'])->name('potenzialanalyse.projekt.uebungen.update');
        Route::delete('/potenzialanalyse/uebungen/{uebung}', [PotenzialanalyseController::class, 'destroyUebung'])->name('potenzialanalyse.projekt.uebungen.destroy');
        Route::post('/potenzialanalyse/uebungen/{uebung}/kriterien', [PotenzialanalyseController::class, 'storeKriterium'])->name('potenzialanalyse.projekt.kriterien.store');
        Route::put('/potenzialanalyse/kriterien/{kriterium}', [PotenzialanalyseController::class, 'updateKriterium'])->name('potenzialanalyse.projekt.kriterien.update');
        Route::delete('/potenzialanalyse/kriterien/{kriterium}', [PotenzialanalyseController::class, 'destroyKriterium'])->name('potenzialanalyse.projekt.kriterien.destroy');
    });

    // Export-Vorlagen / Dokumentenmanager
    Route::get('/dokumente', [DokumenteController::class, 'index'])->name('dokumente.index');
    Route::post('/dokumente', [DokumenteController::class, 'store'])->name('dokumente.store');
    Route::get('/dokumente/{dokument}/download', [DokumenteController::class, 'download'])->name('dokumente.download');
    Route::put('/dokumente/{dokument}', [DokumenteController::class, 'update'])->name('dokumente.update');
    Route::post('/dokumente/kategorien', [DokumenteController::class, 'storeKategorie'])->name('dokumente.kategorien.store');
    Route::put('/dokumente/projekt-kategorien/{projekt}', [DokumenteController::class, 'updateProjektKategorien'])->name('dokumente.projekt-kategorien.update');

    //Gruppe
    Route::middleware('projectFeature:group_management')->group(function () {
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
    Route::put('/gruppe/{gruppe}/potenzialanalyse/teilnehmer/{personen}', [PotenzialanalyseController::class, 'updateTeilnehmer'])->name('potenzialanalyse.gruppe.teilnehmer.update')->middleware(['module:participant_management', 'projectFeature:participant_management', 'projectFeature:potential_analysis']);

    //GruppeHasTeilnehmer
    Route::get('/gruppehasteilnehmer/{id}', [GruppeHasTeilnehmerController::class, 'show'])->name('gruppeHasTeilnehmer.show')->middleware(['module:participant_management', 'projectFeature:participant_management']);
    Route::post('/gruppehasteilnehmer/anlegen', [GruppeHasTeilnehmerController::class, 'store'])->name('gruppeHasTeilnehmer.store')->middleware(['module:participant_management', 'projectFeature:participant_management']);

    Route::delete('/gruppehasteilnehmer/gruppe/{gruppe}/teilnehmer/{personen}', [GruppeHasTeilnehmerController::class, 'destroyTeilnehmer'])->name('gruppeHasTeilnehmer.destroyTeilnehmer')->middleware(['module:participant_management', 'projectFeature:participant_management']);
    Route::delete('/gruppehasteilnehmer/entfernen/{id}', [GruppeHasTeilnehmerController::class, 'destroy'])->name('gruppeHasPersonen.destroy')->middleware(['module:participant_management', 'projectFeature:participant_management']);
    });

    //Klassenbuch
    Route::middleware('projectFeature:classbook_management')->group(function () {
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
    });

    //Teilnehmer
    Route::middleware(['module:participant_management', 'projectFeature:participant_management'])->group(function () {
    Route::get('/teilnehmer', [TeilnehmerController::class, 'index'])->name('teilnehmer.index')->can('teilnehmer.index');
    Route::get('/teilnehmer/anlegen', [TeilnehmerController::class, 'create'])->name('teilnehmer.create')->can('teilnehmer.store');
    Route::post('/teilnehmer/anlegen', [TeilnehmerController::class, 'store'])->name('teilnehmer.store')->can('teilnehmer.store');
    Route::post('/teilnehmer/import', [TeilnehmerController::class, 'import'])->name('teilnehmer.import')->middleware('canAnyPermission:teilnehmer.import,teilnehmer.store');
    Route::delete('/teilnehmer/entfernen', [TeilnehmerController::class, 'bulkDestroy'])->name('teilnehmer.bulkDestroy')->middleware('canAnyPermission:teilnehmer.bulkDestroy,teilnehmer.destroy');
    Route::delete('/teilnehmer/entfernen/{id}', [TeilnehmerController::class, 'destroy'])->name('teilnehmer.destroy')->can('teilnehmer.destroy');
    Route::get('/teilnehmer/bearbeiten/{id}', [TeilnehmerController::class, 'show'])->name('teilnehmer.edit')->can('teilnehmer.update');
    Route::patch('/teilnehmer/update/{id}', [TeilnehmerController::class, 'update'])->name('teilnehmer.update')->can('teilnehmer.update');
    Route::get('/teilnehmer/{person}/lebenslauf', [ParticipantCvController::class, 'staffIndex'])->name('teilnehmer.resume.index')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/{person}/lebenslauf/eintraege', [ParticipantCvController::class, 'store'])->name('teilnehmer.resume.entries.store')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/{person}/lebenslauf/versionen', [ParticipantCvController::class, 'createVersion'])->name('teilnehmer.resume.versions.store')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/lebenslauf/eintraege/{entry}', [ParticipantCvController::class, 'update'])->name('teilnehmer.resume.entries.update')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::delete('/teilnehmer/lebenslauf/eintraege/{entry}', [ParticipantCvController::class, 'destroy'])->name('teilnehmer.resume.entries.destroy')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::get('/teilnehmer/lebenslauf/versionen/{version}/download', [ParticipantCvController::class, 'download'])->name('teilnehmer.resume.versions.download')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::get('/teilnehmer/lebenslauf/versionen/{version}/vorschau', [ParticipantCvController::class, 'print'])->name('teilnehmer.resume.versions.print')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/teilnahme/{participation}/aufnahmecheckliste/{item}', [IntakeChecklistController::class, 'updateCompletion'])->name('teilnehmer.intake-checklist.update')->can('teilnehmer.update');
    Route::put('/teilnehmer/teilnahme/{participation}/abschlusscheckliste/{item}', [ParticipationCompletionController::class, 'updateCompletion'])->name('teilnehmer.completion-checklist.update')->middleware('projectFeature:completion_management')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/abschlussbericht', [ParticipationCompletionController::class, 'submit'])->name('teilnehmer.completion-reports.submit')->middleware('projectFeature:completion_management')->can('teilnehmer.update');
    Route::put('/teilnehmer/abschlussbericht/{report}/entscheidung', [ParticipationCompletionController::class, 'decide'])->name('teilnehmer.completion-reports.decide')->middleware('projectFeature:completion_management')->can('teilnehmer.update');
    Route::get('/teilnehmer/abschlussbericht/{report}/export', [ParticipationCompletionController::class, 'export'])->name('teilnehmer.completion-reports.export')->middleware('projectFeature:completion_management')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/portal-einladung', [ParticipantPortalController::class, 'invite'])->name('teilnehmer.portal.invite')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/aufgaben', [ParticipationTaskController::class, 'store'])->name('teilnehmer.tasks.store')->can('teilnehmer.update');
    Route::put('/teilnehmer/aufgaben/{task}', [ParticipationTaskController::class, 'update'])->name('teilnehmer.tasks.update')->can('teilnehmer.update');
    Route::delete('/teilnehmer/aufgaben/{task}', [ParticipationTaskController::class, 'destroy'])->name('teilnehmer.tasks.destroy')->can('teilnehmer.update');
    Route::put('/teilnehmer/bewerbungen/{application}', [ParticipationApplicationController::class, 'update'])->name('teilnehmer.applications.update')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/bewerbungen/{application}/dokumente', [ParticipantApplicationPackageController::class, 'staffSync'])->name('teilnehmer.applications.documents.sync')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/bewerbungen/{application}/freigabe', [ParticipantApplicationPackageController::class, 'staffApprove'])->name('teilnehmer.applications.package.approve')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/stellenempfehlungen', [ParticipantJobRecommendationController::class, 'staffStore'])->name('teilnehmer.recommendations.store')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/anwesenheit/korrekturen/{correction}', [AttendanceCorrectionController::class, 'resolve'])->name('teilnehmer.attendance.corrections.resolve')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/portal-dokumente', [ParticipantDocumentController::class, 'store'])->name('teilnehmer.portal-documents.store')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/portal-dokumente/{document}/pruefen', [ParticipantDocumentController::class, 'review'])->name('teilnehmer.portal-documents.review')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::get('/teilnehmer/portal-dokumente/{document}/download', [ParticipantDocumentController::class, 'download'])->name('teilnehmer.portal-documents.download')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::post('/teilnehmer/teilnahme/{participation}/nachrichten', [ParticipantMessageController::class, 'staffStore'])->name('teilnehmer.messages.store')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/teilnahme/{participation}/nachrichten/gelesen', [ParticipantMessageController::class, 'staffRead'])->name('teilnehmer.messages.read')->middleware('module:participant_portal')->can('teilnehmer.update');
    Route::put('/teilnehmer/datenauskunft/{dataRequest}', [ParticipantDataRequestController::class, 'resolve'])->name('teilnehmer.data-requests.resolve')->middleware('module:participant_portal')->middleware('canAnyPermission:teilnehmer.data-request.manage,teilnehmer.update');
    Route::patch('/teilnehmer/{person}/sozialdaten', [TeilnehmerController::class, 'updateSozialdaten'])->name('person.sozialdaten.update')->middleware('canAnyPermission:person.sozialdaten.update,teilnehmer.update');
    Route::get('/teilnehmer/{id}', [TeilnehmerController::class, 'indexNachProjekt'])->name('teilnehmer.projekt.index')->middleware('canAnyPermission:teilnehmer.projekt.index,teilnehmer.index');

    // Personen Has Abschluss
    Route::post('/teilnehmer/abschluss/anlegen', [AbschlusseController::class, 'store'])->name('abschluss.store')->can('teilnehmer.update')->middleware('projectFeature:completion_management');
    Route::delete('/teilnehmer/abschluss/entfernen/{id}', [AbschlusseController::class, 'destroy'])->name('abschluss.destroy')->can('teilnehmer.update')->middleware('projectFeature:completion_management');

    // Personen Has Praktikum
    Route::post('/teilnehmer/praktikum/anlegen', [PersonenHasBildungsmassnahmenController::class, 'store'])->name('teilnehmer.praktikum.store')->can('teilnehmer.update')->middleware('projectFeature:internship_management');
    Route::put('/teilnehmer/praktikum/{measure}', [PersonenHasBildungsmassnahmenController::class, 'update'])->name('teilnehmer.praktikum.update')->can('teilnehmer.update')->middleware('projectFeature:internship_management');
    Route::delete('/teilnehmer/praktikum/{measure}', [PersonenHasBildungsmassnahmenController::class, 'destroy'])->name('teilnehmer.praktikum.destroy')->can('teilnehmer.update')->middleware('projectFeature:internship_management');
    });

    //Räumlichkeiten
    Route::middleware('module:room_management')->group(function () {
        Route::get('/ressourcen/standort/raeumlichkeiten/', [RaumlichkeitenController::class, 'index'])->name('raeumlichkeiten.index');
        Route::post('/ressourcen/standort/raeumlichkeiten/anlegen', [RaumlichkeitenController::class, 'store'])->name('raeumlichkeiten.store');
        Route::put('/ressourcen/standort/raeumlichkeiten/update/{id}', [RaumlichkeitenController::class, 'update'])->name('raeumlichkeiten.update');
        Route::delete('/ressourcen/standort/raeumlichkeiten/entfernen/{id}', [RaumlichkeitenController::class, 'destroy'])->name('raeumlichkeiten.destroy');
        Route::post('/ressourcen/standort/raeumlichkeiten/{raum}/meldung', [RaumlichkeitenController::class, 'storeMeldung'])->name('raeumlichkeiten.meldung.store');
        Route::put('/ressourcen/standort/raeumlichkeiten/meldung/{meldung}', [RaumlichkeitenController::class, 'updateMeldung'])->name('raeumlichkeiten.meldung.update');
        Route::post('/ressourcen/standort/raeumlichkeiten/buchung', [RaumlichkeitenController::class, 'storeBuchung'])->name('raeumlichkeiten.buchung.store');
        Route::put('/ressourcen/standort/raeumlichkeiten/buchung/{buchung}', [RaumlichkeitenController::class, 'updateBuchung'])->name('raeumlichkeiten.buchung.update');
        Route::delete('/ressourcen/standort/raeumlichkeiten/buchung/{buchung}', [RaumlichkeitenController::class, 'destroyBuchung'])->name('raeumlichkeiten.buchung.destroy');
    });

    //Anwesenheiten
    Route::middleware('projectFeature:attendance_management')->group(function () {
    Route::post('/anwesenheit/speichern', [AnwesenheitController::class, 'store'])->name('anwesenheit.store');
    Route::delete('/anwesenheit/entfernen/{id}', [AnwesenheitController::class, 'destroy'])->name('anwesenheit.destroy');
    Route::post('/anwesenheit/update', [AnwesenheitController::class, 'update'])->name('anwesenheit.update');
    });

    //Kontakte
    Route::middleware(['module:participant_management', 'projectFeature:participant_management'])->group(function () {
    Route::delete('/teilnehmer/kontakt/entfernen/{id}', [KontaktController::class, 'destroy'])->name('kontakt.destroy')->can('teilnehmer.update');
    Route::post('/teilnehmer/kontakt/anlegen', [KontaktController::class, 'store'])->name('kontakt.store')->can('teilnehmer.update');

    //Adresse
    Route::post('/teilnehmer/adresse/anlegen', [AdresseController::class, 'store'])->name('adresse.store')->can('teilnehmer.update');
    Route::delete('/teilnehmer/adresse/entfernen/{id}', [AdresseController::class, 'destroy'])->name('adresse.destroy')->can('teilnehmer.update');

    //ProjektHasTeilnehmer
    Route::post('/teilnehmer/projekt/anlegen', [ProjektHasTeilnehmerController::class, 'store'])->name('projekthasteilnehmer.store')->middleware('canAnyPermission:projekthasteilnehmer.store,teilnehmer.update');
    Route::put('/teilnehmer/projekt/edit', [ProjektHasTeilnehmerController::class, 'update'])->name('projekthasteilnehmer.update')->middleware('canAnyPermission:projekthasteilnehmer.update,teilnehmer.update');

    //ProjektHasTeilnehmerLuv
    Route::post('/teilnehmer/projekt/luv/anlegen', [ProjektHasTeilnehmerLuvController::class, 'store'])->name('projekthasteilnehmer.luv.store')->middleware('canAnyPermission:projekthasteilnehmer.luv.store,teilnehmer.update');
    Route::put('/teilnehmer/projekt/luv/edit', [ProjektHasTeilnehmerLuvController::class, 'update'])->name('projekthasteilnehmer.luv.update')->middleware('canAnyPermission:projekthasteilnehmer.luv.update,teilnehmer.update');
    Route::delete('/teilnehmer/projekt/luv/entfernen/{id}', [ProjektHasTeilnehmerLuvController::class, 'destroy'])->name('projekthasteilnehmer.luv.destroy')->middleware('canAnyPermission:projekthasteilnehmer.luv.destroy,teilnehmer.update');
    Route::get('/teilnehmer/projekt/luv/export/{id}', [ProjektHasTeilnehmerLuvController::class, 'export'])->name('projekthasteilnehmer.luv.export')->middleware('canAnyPermission:projekthasteilnehmer.luv.export,teilnehmer.index');
    });

    //ProjektHasPersonen
    Route::post('/personen/projekt/zuweisen', [ProjektHasPersonenController::class, 'store'])->name('projekthaspersonen.store')->can('benutzer.update');
    Route::delete('/personen/projekt/entfernen/{id}', [ProjektHasPersonenController::class, 'destroy'])->name('projekthaspersonen.destroy')->can('benutzer.update');

    //Teilnehmer Bank
    Route::middleware(['module:participant_management', 'projectFeature:participant_management'])->group(function () {
        Route::post('/teilnehmer/bank/anlegen', [BaenkeController::class, 'store'])->name('bank.store')->can('teilnehmer.update');
        Route::delete('/teilnehmer/bank/entfernen/{id}', [BaenkeController::class, 'destroy'])->name('bank.destroy')->can('teilnehmer.update');
    });

    //Partner
    Route::get('/organisation/partner', [PartnerController::class, 'index'])->name('partner.index');
    Route::get('/partner', [PartnerController::class, 'index'])->name('dashboard.partner.index');
    Route::post('/organisation/partner/anlegen', [PartnerController::class, 'store'])->name('partner.store');
    Route::delete('/organisation/partner/entfernen/{id}', [PartnerController::class, 'destroy'])->name('partner.destroy');
    Route::put('/organisation/partner/edit/{id}', [PartnerController::class, 'update'])->name('partner.update');
    Route::get('/organisation/partner/ajax/fresh', [PartnerController::class, 'indexAjaxFresh'])->name('partner.indexAjaxFresh');
    Route::post('/organisation/partner/{partner}/bop-usb-stick-brief', [PartnerController::class, 'exportBopUsbStickLetter'])->name('partner.bop-usb-stick-letter.export');

    //Kostenstelle
    Route::get('/kostenstelle', [KostenstelleController::class, 'index'])->name('kostenstelle.index');
    Route::post('/kostenstelle/anlegen', [KostenstelleController::class, 'store'])->name('kostenstelle.store');

    Route::get('/design/responsive', function () {
        return Inertia::render('Design/Responsive');
    })->name('responsive');

    //Notification
    Route::get('/user/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.unread');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    //Brief
        Route::post('/brief', [BriefController::class, 'store'])->name('brief.store');
        Route::post('/brief/share', [BriefController::class, 'share'])->name('brief.share');
        Route::delete('/brief/delete/{id}', [BriefController::class, 'destroy'])->name('brief.destroy');
        Route::delete('/brief/shared/delete/{id}', [BriefController::class, 'sharedDestroy'])->name('briefShared.destroy');

    //Notizen
        Route::post('/notizen', [NotizController::class, 'store'])->name('notizen.store');
        Route::delete('/notizen/delete/{id}', [NotizController::class, 'destroy'])->name('notizen.destroy');

        //Export
        Route::get('/teilnehmer/export/stammblatt/{teilnehmerId}/{projektId}', [ExportExcelController::class, 'esfStammblatt'])->name('export.excel.esfStammblatt')->middleware(['module:participant_management', 'projectFeature:participant_management']);

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
        Route::get('/dienstwagen', [DienstwagenController::class, 'index'])->name('index')->can('dienstwagen.index');
        Route::get('/dienstwagen/edit/{id}', [DienstwagenController::class, 'edit'])->name('edit')->can('dienstwagen.edit');
        Route::get('/dienstwagen/create', [DienstwagenController::class, 'create'])->name('create')->can('dienstwagen.create');
        Route::get('/dienstwagen/{id}/verlauf', [DienstwagenController::class, 'verlauf'])->name('verlauf.index')->can('dienstwagen.verlauf.index');
        Route::get('/dienstwagen/{id}/fahrtenbuch-code', [DienstwagenController::class, 'fahrtenbuchCode'])->name('fahrtenbuch.code')->can('dienstwagen.fahrtenbuch.index');
        Route::post('/dienstwagen', [DienstwagenController::class, 'store'])->name('store')->can('dienstwagen.store');
        Route::delete('/dienstwagen/{wagen}', [DienstwagenController::class, 'destroy'])->name('destroy')->can('dienstwagen.destroy');
        Route::put('dienstwagen/update/{id}', [DienstwagenController::class, 'update'])->name('update')->can('dienstwagen.update');

        // Fahrer
        Route::get('/drivers', [DienstwagenController::class, 'index'])->name('drivers.index')->can('dienstwagen.drivers.index');

        // Wartung
        Route::get('/wartung', [DienstwagenwartungController::class, 'index'])->name('wartung.index')->can('dienstwagen.wartung.index');
        Route::post('/wartung', [DienstwagenwartungController::class, 'store'])->name('wartung.store')->can('dienstwagen.wartung.store');
        Route::put('/wartung/edit/{id}', [DienstwagenwartungController::class, 'update'])->name('wartung.update')->can('dienstwagen.wartung.update');
        Route::delete('/wartung/{id}', [DienstwagenwartungController::class, 'destroy'])->name('wartung.destroy')->can('dienstwagen.wartung.destroy');

        // Kosten
        Route::get('/kosten', [DienstwagenkostenController::class, 'index'])->name('kosten.index')->can('dienstwagen.kosten.index');
        Route::post('/kosten', [DienstwagenkostenController::class, 'store'])->name('kosten.store')->can('dienstwagen.kosten.store');
        Route::put('/kosten/{id}', [DienstwagenkostenController::class, 'update'])->name('kosten.update')->can('dienstwagen.kosten.update');
        Route::delete('/kosten/{id}', [DienstwagenkostenController::class, 'destroy'])->name('kosten.destroy')->can('dienstwagen.kosten.destroy');

        // Buchungen
        Route::get('/buchungen', [DienstwagenBuchungController::class, 'index'])->name('buchungen.index')->can('dienstwagen.buchungen.index');
        Route::post('/buchungen', [DienstwagenBuchungController::class, 'store'])->name('buchungen.store')->can('dienstwagen.buchungen.store');
        Route::put('/buchungen/{id}', [DienstwagenBuchungController::class, 'update'])->name('buchungen.update')->can('dienstwagen.buchungen.update');
        Route::delete('/buchungen/{id}', [DienstwagenBuchungController::class, 'destroy'])->name('buchungen.destroy')->can('dienstwagen.buchungen.destroy');

        // Meldungen
        Route::get('/meldungen', [DienstwagenMeldungController::class, 'index'])->name('meldungen.index')->can('dienstwagen.meldungen.index');
        Route::post('/meldungen', [DienstwagenMeldungController::class, 'store'])->name('meldungen.store')->can('dienstwagen.meldungen.store');
        Route::put('/meldungen/{id}', [DienstwagenMeldungController::class, 'update'])->name('meldungen.update')->can('dienstwagen.meldungen.update');
        Route::delete('/meldungen/{id}', [DienstwagenMeldungController::class, 'destroy'])->name('meldungen.destroy')->can('dienstwagen.meldungen.destroy');

        // Berichte
        Route::get('/dienstwagen/reports', [DienstwagenreportsController::class, 'index'])->name('reports.index')->can('dienstwagen.reports.index');

        // Fahrtenbuch
        Route::get('/fahrtenbuch', [DienstwagenfahrtenbuchController::class, 'index'])->name('fahrtenbuch.index')->can('dienstwagen.fahrtenbuch.index');
        Route::post('/fahrtenbuch', [DienstwagenfahrtenbuchController::class, 'store'])->name('fahrtenbuch.store')->can('dienstwagen.fahrtenbuch.store');
        Route::put('/fahrtenbuch/edit/{id}', [DienstwagenfahrtenbuchController::class, 'update'])->name('fahrtenbuch.update')->can('dienstwagen.fahrtenbuch.update');
        Route::delete('/fahrtenbuch/{id}', [DienstwagenfahrtenbuchController::class, 'destroy'])->name('fahrtenbuch.destroy')->can('dienstwagen.fahrtenbuch.destroy');
        Route::get('/fahrtenbuch/report', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchReport'])->name('fahrtenbuch.report')->can('dienstwagen.fahrtenbuch.report');
        Route::get('/fahrtenbuch/report/pdf', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchPDF'])->name('fahrtenbuch.report.pdf')->can('dienstwagen.fahrtenbuch.report.pdf');
        Route::get('/fahrtenbuch/report/excel', [DienstwagenfahrtenbuchController::class, 'generateFahrtenbuchExcel'])->name('fahrtenbuch.report.excel')->can('dienstwagen.fahrtenbuch.report.excel');
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
    Route::get('/ausleihende', [GeraetController::class, 'indexAusleihende'])->name('geraet.index.ausleihende')->middleware('canAnyPermission:geraet.index.ausleihende,index-ausleihende');

    /*   Gerät Ausgabe */
    Route::get('/ressourcen/geraetausgabe', [GeraetausgabeController::class,'index'])->name('geraet.ausgabe.index')->can('geraet.ausgabe.index');
    Route::post('/ressourcen/geraetausgabe', [GeraetausgabeController::class,'store'])->name('geraet.ausgabe.store')->can('geraet.ausgabe.store');
    Route::delete('/ressourcen/geraetausgabe/{id}', [GeraetausgabeController::class, 'destroy'])->name('geraetausgabe.destroy')->can('geraet.ausgabe.destroy');
    Route::get('/ressourcen/geraetausgabe-view/{id}', [GeraetausgabeController::class, 'view'])->name('ausgabe.view')->can('geraet.ausgabe.index');
    Route::get('/ressourcen/geraetausgabe-excel/{id}', [GeraetausgabeController::class, 'exportExcel'])->name('geraet.ausgabe.export.excel')->can('geraet.ausgabe.export.excel');
    Route::post('/ressourcen/geraetausgabe-store-add', [GeraetausgabeController::class, 'storeAdd'])->name('geraet.ausgabe.store.add')->can('geraet.ausgabe.store.add');

    /*   Gerät Rückgabe */
    Route::get('/ressourcen/geraet/rueckgabe', [GeraetrueckgabeController::class,'index'])->name('geraet.rueckgabe.index')->can('geraet.rueckgabe.index');
    Route::post('/ressourcen/geraet/rueckgabe', [GeraetrueckgabeController::class,'store'])->name('geraet.rueckgabe.store')->can('geraet.rueckgabe.store');
    Route::delete('/ressourcen/geraetrueckgabe/{id}', [GeraetrueckgabeController::class, 'destroy'])->name('geraetrueckgabe.destroy')->can('geraet.rueckgabe.destroy');
    Route::get('/ressourcen/geraetrueckgabe-view/{id}', [GeraetrueckgabeController::class, 'view'])->name('rueckgabe.view')->can('geraet.rueckgabe.index');
    Route::get('/ressourcen/geraetrueckgabe-excel/{id}', [GeraetrueckgabeController::class, 'exportExcel'])->name('geraet.rueckgabe.export.excel')->can('geraet.rueckgabe.export.excel');
    Route::post('/ressourcen/geraetrueckgabe-store-add', [GeraetrueckgabeController::class, 'storeAdd'])->name('geraet.rueckgabe.store.add')->can('geraet.rueckgabe.store.add');
    Route::get('/ressourcen/geraet/rueckgabe/{id}/geraete', [GeraetrueckgabeController::class, 'geraete'])->name('geraet.rueckgabe.geraete')->can('geraet.rueckgabe.geraete');

    /*   IT-Service */
    Route::prefix('ressourcen/it-service')->name('it-service.')->group(function () {
        Route::get('/', [ItServiceController::class, 'index'])->name('index');
        Route::post('/tickets', [ItServiceController::class, 'storeTicket'])->name('tickets.store');
        Route::put('/tickets/{ticket}', [ItServiceController::class, 'updateTicket'])->name('tickets.update');
        Route::delete('/tickets/{ticket}', [ItServiceController::class, 'destroyTicket'])->name('tickets.destroy');
        Route::post('/geraete', [ItServiceController::class, 'storeGeraet'])->name('geraete.store');
        Route::put('/geraete/{geraet}', [ItServiceController::class, 'updateGeraet'])->name('geraete.update');
        Route::delete('/geraete/{geraet}', [ItServiceController::class, 'destroyGeraet'])->name('geraete.destroy');
    });

    /*   Internes Lager */
    Route::prefix('ressourcen/lager')->name('lager.')->group(function () {
        Route::get('/', [LagerController::class, 'index'])->name('index');
        Route::post('/artikel', [LagerController::class, 'storeArtikel'])->name('artikel.store');
        Route::put('/artikel/{artikel}', [LagerController::class, 'updateArtikel'])->name('artikel.update');
        Route::delete('/artikel/{artikel}', [LagerController::class, 'destroyArtikel'])->name('artikel.destroy');
        Route::post('/artikel/{artikel}/bewegungen', [LagerController::class, 'storeBewegung'])->name('bewegung.store');
        Route::post('/artikel/{artikel}/reservierungen', [LagerController::class, 'storeReservierung'])->name('reservierung.store');
        Route::put('/reservierungen/{reservierung}', [LagerController::class, 'updateReservierung'])->name('reservierung.update');
    });

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
    Route::post('export-anwesenheitsliste/pa/draft/clear', [ProjektBopController::class, 'anwesenheitslistePADraftDestroy'])->name('anwesenheitsliste.PA.digital.draft.clear');
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
Route::get('/teilnehmerliste/excel/{schuleId}/{schuljahr}/{teil}', [BopLegacyFunctionController::class, 'teilnehmerliste'])->name('export.teilnehmerliste.schule.excel')->middleware(['module:participant_management', 'projectFeature:participant_management']);
Route::get('/teilnehmerccliste/excel/{schuleId}/{schuljahr}/{teil}', [MaterialanforderungController::class, 'index'])->name('teilnehmer.liste.schule')->middleware(['module:participant_management', 'projectFeature:participant_management']);
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
