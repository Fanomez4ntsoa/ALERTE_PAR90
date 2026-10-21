<?php

/*
 * Étape 6 : trois onglets, onglet et mois dans l'adresse, aucune ressource
 * chargée depuis un domaine externe. Données inventées.
 */

use App\Import\ServiceImport;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)), analyste());
    $this->actingAs(direction());
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

function proprietes(string $adresse): array
{
    return test()->get($adresse)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('TableauDeBord'))
        ->viewData('page')['props'];
}

it('répond à chaque onglet à son adresse', function (string $adresse, string $onglet) {
    $props = proprietes($adresse);

    expect($props['onglet'])->toBe($onglet)
        ->and($props['tableau']['mois']['valeur'])->toBe('2024-03')
        ->and(collect($props['onglets'])->pluck('libelle')->all())->toBe(['Synthèse', 'Compréhension', 'Registre']);
})->with([
    ['/dashboard/2024-03', 'synthese'],
    ['/dashboard/2024-03/synthese', 'synthese'],
    ['/dashboard/2024-03/comprehension', 'comprehension'],
    ['/dashboard/2024-03/registre', 'registre'],
]);

it('garde le mois et l\'onglet au rechargement et en changeant de mois', function () {
    $premier = proprietes('/dashboard/2024-03/comprehension');
    $recharge = proprietes('/dashboard/2024-03/comprehension');

    expect($recharge['onglet'])->toBe('comprehension')
        ->and($recharge['tableau']['mois']['valeur'])->toBe('2024-03')
        ->and($recharge['navigation'])->toBe($premier['navigation'])
        ->and($recharge['navigation']['precedent']['url'])->toBe(url('/dashboard/2024-02/comprehension'))
        ->and($recharge['navigation']['suivant']['url'])->toBe(url('/dashboard/2024-04/comprehension'))
        ->and(collect($recharge['navigation']['liste'])->pluck('url')->every(fn ($u) => str_ends_with($u, '/comprehension')))->toBeTrue()
        ->and(collect($recharge['onglets'])->pluck('url', 'cle')->all())->toBe([
            'synthese' => url('/dashboard/2024-03'),
            'comprehension' => url('/dashboard/2024-03/comprehension'),
            'registre' => url('/dashboard/2024-03/registre'),
        ]);

    // Sur la Synthèse, les adresses restent sans suffixe.
    expect(proprietes('/dashboard/2024-03')['navigation']['precedent']['url'])->toBe(url('/dashboard/2024-02'));
});

it("redirige l'ancien onglet Générations vers Compréhension", function () {
    $this->get('/dashboard/2024-03/generations')
        ->assertStatus(301)
        ->assertRedirect(url('/dashboard/2024-03/comprehension'));

    expect(proprietes('/dashboard/2024-03/comprehension')['tableau']['generations']['lignes'])->toHaveCount(12);
});

it('refuse un onglet inconnu', function () {
    $this->get('/dashboard/2024-03/parametres')->assertNotFound();
});

it('ne charge aucune ressource depuis un domaine externe', function () {
    $pages = [
        $this->get('/dashboard/2024-03')->getContent(),
        $this->get('/dashboard/2024-03/registre')->getContent(),
        $this->get(route('admin.journal'))->getContent(),
    ];
    auth()->logout();
    $pages[] = $this->get(route('login'))->getContent();
    $hote = parse_url(config('app.url'), PHP_URL_HOST);

    foreach ($pages as $html) {
        preg_match_all('/<(?:script|link|img|iframe)\b[^>]*\b(?:src|href)="(https?:)?\/\/([^\/"]+)/i', $html, $trouves);
        $hotes = array_map(fn (string $h) => strtolower(explode(':', $h)[0]), $trouves[2]);
        expect(array_values(array_diff($hotes, [$hote, '127.0.0.1', 'localhost'])))->toBe([]);
    }

    // Feuilles de style compilées : aucune police ni image distante.
    foreach (glob(public_path('build/assets/*.css')) as $css) {
        expect(preg_match('/url\(\s*["\']?(https?:)?\/\//i', file_get_contents($css)))->toBe(0, basename($css));
    }

    // La police est embarquée dans le dépôt, avec sa licence.
    expect(glob(resource_path('fonts/*.woff2')))->not->toBeEmpty()
        ->and(file_exists(resource_path('fonts/OFL.txt')))->toBeTrue();
});
