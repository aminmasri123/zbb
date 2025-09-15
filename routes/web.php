<?php

use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\BerechtigungController;
use App\Http\Controllers\BereichController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjektController;
use App\Http\Controllers\SchuleController;
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
Route::middleware(['auth', 'verified', 'injectUserPermissions', 'injectUserProjekte'])->group(function() {


    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard')->can('dashboard.index');

    Route::get('/organisation', function () {
        return Inertia::render('Dashboards/Organisation');
    })->name('organisation.index');

    Route::get('/ressourcen', function () {
        return Inertia::render('Dashboards/Ressourcen');
    })->name('ressourcen.index');


    Route::get('/schule', [SchuleController::class, 'index'])->name('schule.index');


    // Benutzer
    Route::get('/benutzer', [UserController::class, 'index'])->name('user.index');
    Route::get('/benutzer/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
    Route::put('/benutzer/update/{user}', [UserController::class, 'update'])->name('user.update');




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


    //Teilnehmer
    Route::get('/teilnehmer', [TeilnehmerController::class, 'index'])->name('teilnehmer.index');
    Route::get('/teilnehmer/{id}', [TeilnehmerController::class, 'indexNachProjekt'])->name('teilnehmer.projekt.index');

    Route::get('/teilnehmer/anlegen', function () { return Inertia::render('Teilnehmer/CreateTeilnehmer'); })->name('teilnehmer.create');
    Route::post('/teilnehmer/anlegen', [TeilnehmerController::class, 'store'])->name('teilnehmer.store');
    Route::delete('/teilnehmer/entfernen/{id}', [TeilnehmerController::class, 'destroy'])->name('teilnehmer.destroy');
    Route::put('/teilnehmer/bearbeiten', [TeilnehmerController::class, 'show'])->name('teilnehmer.edit');



    Route::get('/design/responsive', function () {
        return Inertia::render('Design/Responsive');
    })->name('responsive');



    //Notification

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
    ->name('notifications.readAll');
});







