<?php

use App\Http\Controllers\TableauDeBordController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard/{mois?}', TableauDeBordController::class)
        ->where('mois', '\d{4}-(0[1-9]|1[0-2])')
        ->name('dashboard');
});

require __DIR__.'/settings.php';
