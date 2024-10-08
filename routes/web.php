<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\UserController;

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
});




Route::post('/set-locale', function () {
    request()->validate(['locale' => 'required|string']);
    session(['locale' => request('locale')]);
    return response()->json(['success' => true]);
});




// Geschützte Routen
Route::middleware(['auth', 'verified', 'injectUserPermissions'])->group(function() {

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/benutzer', [UserController::class, 'index'])->name('user.index');

    Route::post('/toggleCheck', [UserController::class, 'check'])->name('user.check');




    //Benutzer
    Route::get('/benutzer', [UserController::class, 'index'])->name('user.index');
    Route::get('/benutzer-anlegen', function () { return Inertia::render('User/CreateUser'); })->name('user.create');




    Route::get('/design/responsive', function () {
        return Inertia::render('Design/Responsive');
    })->name('responsive');
});







