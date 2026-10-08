<?php

/*
 * Page du tableau de bord sur des données inventées : accès, navigation,
 * clôture affichée, générations connues à la date de clôture.
 */

use App\Import\ServiceImport;
use App\Models\User;
use Database\Seeders\UtilisateurDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

function chargerSerie(int $nombre = 6): void
{
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', $nombre)));
}

it('envoie les visiteurs non connectés vers la connexion', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it("affiche un message quand aucune clôture n'est importée", function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('TableauDeBord')
            ->where('tableau', null)
            ->where('navigation', null)
            ->where('institution', 'Institution'));
});

it("s'ouvre sur la clôture la plus récente et navigue mois par mois", function () {
    chargerSerie();
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('TableauDeBord')
            ->where('tableau.mois.valeur', '2024-06')
            ->where('tableau.mois.libelle', 'juin 2024')
            ->where('navigation.suivant', null)
            ->where('navigation.precedent.valeur', '2024-05')
            ->where('navigation.precedent.url', route('dashboard', ['mois' => '2024-05']))
            ->where('navigation.liste.0.valeur', '2024-06')
            ->where('navigation.liste.5.valeur', '2024-01')
            ->has('navigation.liste', 6));

    $this->get(route('dashboard', ['mois' => '2024-01']))
        ->assertInertia(fn ($page) => $page
            ->where('tableau.mois.valeur', '2024-01')
            ->where('navigation.precedent', null)
            ->where('navigation.suivant.valeur', '2024-02')
            ->where('tableau.bandeau.niveau', 'NON_EVALUE')
            ->where('tableau.signal.disponible', false)
            ->where('tableau.signal.message', 'Données insuffisantes pour le signal avancé'));
});

it('déclare la page en français', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('<html lang="fr"', false);
});

it('répond 404 pour un mois absent ou mal écrit', function () {
    chargerSerie();
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard', ['mois' => '2025-01']))->assertNotFound();
    $this->get('/dashboard/2024-13')->assertNotFound();
    $this->get('/dashboard/juin')->assertNotFound();
});

it('ne montre que les cellules de générations connues à la clôture affichée', function () {
    chargerSerie();
    $this->actingAs(User::factory()->create());

    $props = $this->get(route('dashboard', ['mois' => '2024-03']))->viewData('page')['props'];
    $lignes = collect($props['tableau']['generations']['lignes'])->keyBy('mois');

    expect($lignes)->toHaveCount(12)
        ->and($lignes->keys()->last())->toBe('mars 24')
        // Janvier 2024 : âges 0 à 2 connus en mars, âge 3 pas encore.
        ->and($lignes['janv. 24']['cellules'][2])->toBe('1,00')
        ->and($lignes['janv. 24']['cellules'][3])->toBeNull()
        ->and($lignes['mars 24']['cellules'][0])->toBe('0,50')
        ->and($lignes['mars 24']['cellules'][1])->toBeNull()
        // Avant la première génération du fichier : connues mais absentes.
        ->and($lignes['avr. 23']['cellules'][0])->toBe('n.r.');
});

it('crée un utilisateur de démonstration qui peut se connecter', function () {
    $this->seed(UtilisateurDemoSeeder::class);
    $this->seed(UtilisateurDemoSeeder::class);

    expect(User::query()->where('email', UtilisateurDemoSeeder::EMAIL)->count())->toBe(1)
        ->and(User::query()->where('email', UtilisateurDemoSeeder::EMAIL)->first()->hasVerifiedEmail())->toBeTrue();

    $this->post(route('login.store'), ['email' => UtilisateurDemoSeeder::EMAIL, 'password' => UtilisateurDemoSeeder::MOT_DE_PASSE])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
});
