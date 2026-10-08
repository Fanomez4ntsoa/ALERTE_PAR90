<?php

use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\ParametresController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SecurityController;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Back-office (étape 5) : toute la gestion se fait ici, pas sur le tableau de bord.
 * Imports : analyste. Journal et paramètres : les deux rôles. Profil, mot de
 * passe, apparence : repris de l'espace « settings » du kit.
 */
Route::middleware(['auth', 'verified', 'can:consulter'])->prefix('admin')->group(function () {
    Route::get('/', fn (Request $request) => to_route(
        $request->user()->can('create', Import::class) ? 'admin.imports' : 'admin.journal'
    ))->name('admin');

    Route::get('imports', [ImportController::class, 'index'])->can('create', Import::class)->name('admin.imports');
    Route::post('imports', [ImportController::class, 'store'])->name('admin.imports.store');
    Route::post('imports/annuler', [ImportController::class, 'annuler'])->name('admin.imports.annuler');

    Route::get('journal', JournalController::class)->name('admin.journal');
    Route::get('parametres', ParametresController::class)->name('admin.parametres');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('appearance', 'admin/Appearance')->name('appearance.edit');
});

// Anciennes adresses de l'espace « settings » du kit.
Route::get('settings/{section?}', fn (?string $section = null) => redirect(match ($section) {
    'profile', 'security', 'appearance' => "/admin/{$section}",
    'password' => '/admin/security',
    default => '/admin',
}, 301))->where('section', 'profile|security|appearance|password');
