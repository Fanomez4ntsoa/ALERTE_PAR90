<?php

/*
 * Annulation du dernier import (section 7), sur des fichiers inventés.
 */

use App\Enums\EtatImport;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Generation;
use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    $serie = FabriqueFichier::serie('2024-01', 22);
    $this->premier = app(ServiceImport::class)->importer(FabriqueFichier::ecrire(array_slice($serie, 0, 20, true)))->import;
    $this->second = app(ServiceImport::class)->importer(FabriqueFichier::ecrire($serie))->import;
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

function verification(string $mois): array
{
    return Cloture::query()->whereDate('mois', "{$mois}-01")->first()->only(['hausse_reelle_3m', 'resultat_verif']);
}

it('retire les mois du dernier import et les vérifications qui en dépendent', function () {
    $analyste = User::factory()->create();
    $generationsAvant = Generation::query()->where('import_id', $this->premier->id)->count();
    expect($this->second->mois_ajoutes)->toBe(['2025-09', '2025-10'])
        ->and(verification('2025-06')['hausse_reelle_3m'])->not->toBeNull()
        ->and(verification('2025-07')['hausse_reelle_3m'])->not->toBeNull();
    $verificationAvril = verification('2025-04');

    $this->artisan('alerte:annuler-import', ['--force' => true, '--utilisateur' => $analyste->email])->assertSuccessful();

    $second = $this->second->refresh();
    expect(Cloture::count())->toBe(20)
        ->and(Cloture::query()->where('import_id', $second->id)->count())->toBe(0)
        ->and(Generation::query()->where('import_id', $second->id)->count())->toBe(0)
        ->and(Generation::query()->where('import_id', $this->premier->id)->count())->toBe($generationsAvant)
        ->and($second->etat)->toBe(EtatImport::ANNULE)
        ->and($second->annule_par)->toBe($analyste->id)
        ->and($second->annule_le)->not->toBeNull()
        // Juin et juillet 2025 étaient vérifiés par septembre et octobre : de nouveau en attente.
        ->and(verification('2025-06'))->toBe(['hausse_reelle_3m' => null, 'resultat_verif' => null])
        ->and(verification('2025-07'))->toBe(['hausse_reelle_3m' => null, 'resultat_verif' => null])
        ->and(verification('2025-04'))->toBe($verificationAvril)
        ->and(Storage::disk(ServiceImport::DISQUE_ARCHIVE)->exists($second->chemin_archive))->toBeTrue();

    $trace = Activity::query()->where('event', 'annulation')->sole();
    expect($trace->causer_id)->toBe($analyste->id)
        ->and($trace->subject_id)->toBe($second->id)
        ->and($trace->properties['mois'])->toBe(['2025-09', '2025-10']);
});

it("annule ensuite l'import précédent, puis refuse quand il n'en reste plus", function () {
    $service = app(ServiceImport::class);
    $service->annulerDernier();
    $service->annulerDernier();

    expect(Cloture::count())->toBe(0)
        ->and(Generation::count())->toBe(0)
        ->and(Import::query()->where('etat', EtatImport::ANNULE)->count())->toBe(2);

    expect(fn () => $service->annulerDernier())->toThrow(RuntimeException::class, 'Aucun import actif à annuler.');
    $this->artisan('alerte:annuler-import', ['--force' => true])->assertFailed();
});

it("permet de réimporter les mois d'un import annulé", function () {
    app(ServiceImport::class)->annulerDernier();

    $rapport = app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 22)));

    expect($rapport->moisAjoutes)->toBe(['2025-09', '2025-10'])
        ->and(verification('2025-06')['hausse_reelle_3m'])->not->toBeNull();
});

it('demande confirmation avant d\'annuler', function () {
    $this->artisan('alerte:annuler-import')
        ->expectsConfirmation("Annuler Import {$this->second->id} ({$this->second->nom_fichier}, {$this->second->created_at->format('d/m/Y H:i')}) : 2 mois ?", 'no')
        ->assertFailed();

    expect($this->second->refresh()->etat)->toBe(EtatImport::ACTIF);
});
