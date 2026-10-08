<?php

use App\Http\Controllers\DecisionController;
use App\Http\Controllers\RecapitulatifController;
use App\Http\Controllers\TableauDeBordController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware(['auth', 'verified', 'can:consulter'])->group(function () {
    // Onglet dans l'adresse : /dashboard/2026-07/generations ; Synthèse par défaut, sans suffixe.
    Route::get('dashboard/{mois?}/{onglet?}', TableauDeBordController::class)
        ->where('mois', '\d{4}-(0[1-9]|1[0-2])')
        ->where('onglet', 'synthese|comprehension|generations|registre')
        ->name('dashboard');

    Route::get('recapitulatif', RecapitulatifController::class)->name('recapitulatif');

    Route::post('dashboard/{mois}/decisions', [DecisionController::class, 'store'])
        ->where('mois', '\d{4}-(0[1-9]|1[0-2])')
        ->name('decisions.store');
    Route::patch('decisions/{decision}/completer', [DecisionController::class, 'completer'])->name('decisions.completer');
    Route::patch('decisions/{decision}/corriger', [DecisionController::class, 'corriger'])->name('decisions.corriger');
    Route::patch('decisions/{decision}/annuler', [DecisionController::class, 'annuler'])->name('decisions.annuler');
});

require __DIR__.'/admin.php';
