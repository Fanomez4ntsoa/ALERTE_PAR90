<?php

/*
 * Vérification à trois mois sur le fichier réel (section 9).
 * Seuls les totaux et mois attendus sont écrits ici. Fichier absent : tests ignorés.
 */

use App\Import\ServiceImport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DonneesReelles;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    if (DonneesReelles::disponibles()) {
        app(ServiceImport::class)->importer(DonneesReelles::CHEMIN);
    }
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Storage::disk(ServiceImport::DISQUE_ARCHIVE)->deleteDirectory(ServiceImport::DOSSIER_ARCHIVE);
});

$sansFichier = ! DonneesReelles::disponibles();
$raison = 'data/donnees_chap3.xlsx absent : tests sur données réelles ignorés.';

it('redonne les totaux de vérification sur les 33 mois', function () {
    $props = $this->get(route('recapitulatif'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Recapitulatif'))
        ->viewData('page')['props'];

    expect($props['lignes'])->toHaveCount(33)
        ->and(collect($props['totaux'])->pluck('nombre', 'libelle')->all())->toBe([
            'Hausse confirmée' => 3,
            "Signal non suivi d'une hausse" => 0,
            "La hausse s'est prolongée" => 1,
            'Pas de nouvelle hausse' => 8,
            'Hausse non signalée' => 5,
            'Rien à signaler' => 10,
            'Sans objet' => 3,
            'En attente' => 3,
        ]);
})->skip($sansFichier, $raison);

it('liste les hausses confirmées et les hausses non signalées', function () {
    $lignes = collect($this->get(route('recapitulatif'))->viewData('page')['props']['lignes']);
    $mois = fn (string $resultat) => $lignes->where('resultat', $resultat)->pluck('mois')->values()->all();

    expect($mois('Hausse confirmée'))->toBe(['mars 2025', 'août 2025', 'février 2026'])
        ->and($mois('Hausse non signalée'))->toBe(['juin 2024', 'février 2025', 'juillet 2025', 'décembre 2025', 'janvier 2026'])
        ->and($mois('Sans objet'))->toBe(['novembre 2023', 'décembre 2023', 'janvier 2024'])
        ->and($mois('En attente'))->toBe(['mai 2026', 'juin 2026', 'juillet 2026']);
})->skip($sansFichier, $raison);

it('montre en mai 2026 le signal de février 2026', function () {
    $signal = $this->get(route('dashboard', ['mois' => '2026-05']))->viewData('page')['props']['tableau']['registre']['signal'];

    expect($signal['mois'])->toBe('février 2026')
        ->and($signal['niveau'])->toBe('Vigilance')
        ->and(str_replace("\u{00A0}", ' ', $signal['hausseReelle']))->toBe('+1,28 point')
        ->and($signal['resultat'])->toBe('Hausse confirmée');
})->skip($sansFichier, $raison);

it("n'écrit jamais « fausse alerte »", function () {
    $pages = [
        $this->get(route('recapitulatif'))->viewData('page')['props'],
        ...collect(['2024-01', '2025-05', '2025-09', '2026-07'])->map(fn ($m) => $this->get(route('dashboard', ['mois' => $m]))->viewData('page')['props'])->all(),
    ];

    expect(mb_strtolower(json_encode($pages, JSON_UNESCAPED_UNICODE)))->not->toContain('fausse alerte');
    expect(mb_strtolower((string) file_get_contents(resource_path('js/components/BlocRegistre.vue')).file_get_contents(resource_path('js/pages/Recapitulatif.vue'))))->not->toContain('fausse alerte');
})->skip($sansFichier, $raison);
