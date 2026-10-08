<?php

/*
 * Étape 5 : accès, rôles et anciennes adresses. Un bouton interdit ne s'affiche
 * pas, et le serveur refuse quand même (403).
 */

use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\Import;
use App\Models\User;
use App\Registre\ServiceDecisions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 8)), analyste());
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

it('renvoie le visiteur non connecté vers la connexion, sur toutes les pages', function (string $adresse) {
    $this->followingRedirects()->get($adresse)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login'));
})->with([
    '/', '/dashboard', '/dashboard/2024-03', '/recapitulatif',
    '/admin', '/admin/imports', '/admin/journal', '/admin/parametres',
    '/admin/profile', '/admin/security', '/admin/appearance',
    '/settings', '/settings/profile',
]);

it('refuse toute page à un compte sans rôle', function () {
    $this->actingAs(User::factory()->sansRole()->create());

    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('recapitulatif'))->assertForbidden();
    $this->get(route('admin.journal'))->assertForbidden();
});

it("interdit à l'analyste d'ajouter ou de modifier une décision", function () {
    $decision = app(ServiceDecisions::class)->ajouter(Cloture::query()->orderByDesc('mois')->first(), [
        'action' => 'Revue', 'responsable' => 'Crédit', 'echeance' => '2024-09-30',
    ], direction());
    $this->actingAs(analyste());

    $this->post(route('decisions.store', ['mois' => '2024-08']), ['action' => 'X', 'responsable' => 'Y', 'echeance' => '30/09/2024'])->assertForbidden();
    $this->patch(route('decisions.completer', $decision), ['resultat' => 'Fait'])->assertForbidden();
    $this->patch(route('decisions.annuler', $decision), ['motif' => 'Erreur'])->assertForbidden();
    app(ServiceDecisions::class)->completer($decision, 'Fait', direction());
    $this->patch(route('decisions.corriger', $decision), ['resultat' => 'Autre'])->assertForbidden();

    expect(Decision::count())->toBe(1)->and($decision->refresh()->resultat)->toBe('Fait');

    // Rien à gérer sur son tableau de bord.
    $registre = $this->get(route('dashboard', ['mois' => '2024-08']))->viewData('page')['props']['tableau']['registre'];
    expect($registre['urlAjout'])->toBeNull()
        ->and($registre['decisions'][0]['urlCorriger'])->toBeNull();
});

it("interdit à la direction d'importer ou d'annuler un import, et lui cache la section", function () {
    $this->actingAs(direction());
    $avant = [Cloture::count(), Import::count()];
    $fichier = UploadedFile::fake()->createWithContent('donnees.xlsx', 'contenu');

    $this->get(route('admin.imports'))->assertForbidden();
    $this->post(route('admin.imports.store'), ['fichier' => $fichier])->assertForbidden();
    $this->post(route('admin.imports.annuler'))->assertForbidden();
    $this->artisan('alerte:importer', ['fichier' => FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 9)), '--utilisateur' => direction()->email])
        ->expectsOutputToContain("n'a pas le rôle analyste")
        ->assertFailed();

    expect([Cloture::count(), Import::count()])->toBe($avant);

    $page = $this->get(route('admin.journal'))->viewData('page');
    expect($page['props']['auth']['droits'])->toMatchArray(['role' => 'Direction', 'importer' => false, 'decider' => true]);
    $this->get(route('admin'))->assertRedirect(route('admin.journal'));
});

it("ouvre le back-office de l'analyste sur les imports", function () {
    $this->actingAs(analyste());

    $this->get(route('admin'))->assertRedirect(route('admin.imports'));
    expect($this->get(route('admin.imports'))->assertOk()->viewData('page')['props']['auth']['droits'])
        ->toMatchArray(['role' => 'Analyste', 'importer' => true, 'decider' => false]);
});

it('exige un utilisateur explicite dans les commandes', function () {
    $this->artisan('alerte:importer', ['fichier' => FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 9))])
        ->expectsOutputToContain('Indiquez l\'utilisateur')
        ->assertFailed();
    $this->artisan('alerte:annuler-import', ['--force' => true])
        ->expectsOutputToContain('Indiquez l\'utilisateur')
        ->assertFailed();

    expect(Import::count())->toBe(1);
});

it('redirige les anciennes adresses /settings vers /admin', function (string $ancienne, string $nouvelle) {
    $this->actingAs(analyste());

    $this->get($ancienne)->assertRedirect($nouvelle)->assertStatus(301);
})->with([
    ['/settings', '/admin'],
    ['/settings/profile', '/admin/profile'],
    ['/settings/security', '/admin/security'],
    ['/settings/password', '/admin/security'],
    ['/settings/appearance', '/admin/appearance'],
]);

it("n'affiche aucun tiret long ni émoji dans les données des pages", function () {
    $this->actingAs(direction());
    $pages = collect([route('dashboard'), route('dashboard', ['mois' => '2024-01']), route('recapitulatif'), route('admin.journal'), route('admin.parametres')])
        ->map(fn ($url) => json_encode($this->get($url)->viewData('page')['props'], JSON_UNESCAPED_UNICODE))
        ->implode("\n");

    expect($pages)->not->toContain('—')
        ->and(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $pages))->toBe(0);
});
