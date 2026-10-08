<?php

/*
 * Données envoyées à la page pour le fichier réel (sections 8 et 12).
 * Seules les valeurs attendues du cahier des charges sont écrites ici.
 * Fichier absent : tests ignorés.
 */

use App\Import\ServiceImport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

/** Props Inertia de la page, espaces insécables remplacés par des espaces simples. */
function propsTableau(TestResponse $reponse): array
{
    $reponse->assertOk()->assertInertia(fn ($page) => $page->component('TableauDeBord'));
    $props = json_decode(json_encode($reponse->viewData('page')['props']), true);
    array_walk_recursive($props, function (&$valeur) {
        if (is_string($valeur)) {
            $valeur = str_replace("\u{00A0}", ' ', $valeur);
        }
    });

    return $props;
}

$sansFichier = ! DonneesReelles::disponibles();
$raison = 'data/donnees_chap3.xlsx absent : tests sur données réelles ignorés.';

it("s'ouvre sur juillet 2026 avec les valeurs de l'annexe H", function () {
    $props = propsTableau($this->get(route('dashboard')));
    $t = $props['tableau'];

    expect($t['mois']['valeur'])->toBe('2026-07')
        ->and($t['bandeau']['niveau'])->toBe('NORMAL')
        ->and($t['bandeau']['libelle'])->toBe('Normal')
        ->and($t['bandeau']['statut']['code'])->toBe('HORS_LIMITE')
        ->and($t['bandeau']['statut']['libelle'])->toBe('Hors limite')
        ->and($t['bandeau']['statut']['texte'])->toBe('Le PAR90 dépasse la limite de 5 %. Point sur le plan de traitement du stock ancien.')
        ->and($t['bandeau']['action'])->toBe('Suivi mensuel habituel')
        ->and($t['bandeau']['mention'])->toBe('calcul rétrospectif')
        ->and($t['risque']['par90'])->toBe('6,25 %')
        ->and($t['risque']['montant'])->toBe('12,79 Md Ar')
        ->and($t['risque']['courbe']['valeurs'])->toHaveCount(33)
        ->and($t['risque']['courbe']['indexMois'])->toBe(32)
        ->and($t['risque']['courbe']['limite'])->toEqual(5)
        ->and($t['trajectoire']['hausse'])->toBe('−0,25 point')
        ->and($t['trajectoire']['seuil'])->toBe('+0,50 point')
        ->and($t['trajectoire']['atteint'])->toBeFalse()
        ->and($t['signal']['disponible'])->toBeTrue()
        ->and($t['signal']['bond'])->toBe('−0,05 point')
        ->and($t['signal']['seuil'])->toBe('0,135 point')
        ->and($t['signal']['nombreBonds'])->toBe(31)
        ->and($t['signal']['voyant'])->toBeFalse()
        ->and($t['signal']['libelleVoyant'])->toBe('Éteint')
        ->and($props['navigation']['suivant'])->toBeNull()
        ->and($props['navigation']['precedent']['valeur'])->toBe('2026-06')
        ->and($props['navigation']['liste'])->toHaveCount(33);

    $indicateurs = collect($t['comprehension']['indicateurs'])->mapWithKeys(fn ($i) => [$i['libelle'] => [$i['valeur'], $i['precedente']]])->all();
    expect($t['comprehension']['moisPrecedent'])->toBe('avril 2026')
        ->and($indicateurs)->toBe([
            'PAR30' => ['8,04 %', '8,62 %'],
            'PAR60' => ['6,97 %', '7,40 %'],
            "Taux d'impayés" => ['8,14 %', '8,10 %'],
            'Tranche 61–90' => ['0,72 %', '0,91 %'],
            'Part des retards de plus de 180 jours dans le PAR90' => ['78,5 %', '66,5 %'],
            "Part des retards de plus d'un an dans le PAR90" => ['41,1 %', '35,8 %'],
            'Taux de passage connu' => ['73,44 %', '84,66 %'],
            'Radiations du mois' => ['0,47 Md Ar', 'n.r.'],
        ]);

    $generations = $t['generations'];
    expect($generations['lignes'])->toHaveCount(12)
        ->and($generations['lignes'][11]['cellules'][0])->not->toBeNull()
        ->and($generations['lignes'][11]['cellules'][1])->toBeNull();
})->skip($sansFichier, $raison);

it('affiche la Vigilance de février 2026 avec le voyant allumé', function () {
    $t = propsTableau($this->get(route('dashboard', ['mois' => '2026-02'])))['tableau'];

    expect($t['bandeau']['niveau'])->toBe('VIGILANCE')
        ->and($t['bandeau']['couleur'])->toBe('jaune')
        ->and($t['signal']['voyant'])->toBeTrue()
        ->and($t['signal']['libelleVoyant'])->toBe('Allumé');
})->skip($sansFichier, $raison);

it("affiche l'Alerte de mars 2026, hausse au niveau du seuil", function () {
    $t = propsTableau($this->get(route('dashboard', ['mois' => '2026-03'])))['tableau'];

    expect($t['bandeau']['niveau'])->toBe('ALERTE')
        ->and($t['bandeau']['couleur'])->toBe('rouge')
        ->and($t['bandeau']['motif'])->toBe('Hausse du PAR90 de +0,52 point sur trois mois.')
        ->and($t['trajectoire']['hausse'])->toBe('+0,52 point')
        ->and($t['trajectoire']['atteint'])->toBeTrue();
})->skip($sansFichier, $raison);

it('affiche janvier 2024 en Non évalué', function () {
    $t = propsTableau($this->get(route('dashboard', ['mois' => '2024-01'])))['tableau'];

    expect($t['bandeau']['niveau'])->toBe('NON_EVALUE')
        ->and($t['bandeau']['libelle'])->toBe('Non évalué')
        ->and($t['bandeau']['couleur'])->toBe('gris')
        ->and($t['bandeau']['action'])->toBe('Aucune')
        ->and($t['trajectoire']['hausse'])->toBe('n.r.')
        ->and($t['trajectoire']['jauge'])->toBeNull()
        ->and($t['signal']['disponible'])->toBeFalse()
        ->and($t['signal']['message'])->toBe('Données insuffisantes pour le signal avancé');
})->skip($sansFichier, $raison);

it('affiche n.r. pour les radiations absentes d\'avril 2026', function () {
    $t = propsTableau($this->get(route('dashboard', ['mois' => '2026-04'])))['tableau'];
    $radiations = collect($t['comprehension']['indicateurs'])->firstWhere('libelle', 'Radiations du mois');

    expect($radiations['valeur'])->toBe('n.r.')
        ->and($radiations['evolution'])->toBeNull();
})->skip($sansFichier, $raison);

it('met décembre 2023 dans la limite', function () {
    $t = propsTableau($this->get(route('dashboard', ['mois' => '2023-12'])))['tableau'];

    expect($t['bandeau']['statut']['code'])->toBe('DANS_LIMITE')
        ->and($t['bandeau']['statut']['libelle'])->toBe('Dans la limite')
        ->and($t['bandeau']['statut']['texte'])->toBeNull()
        ->and($t['risque']['courbe']['indexMois'])->toBe(1);
})->skip($sansFichier, $raison);

it("garde l'échelle de la jauge du bloc 3 d'une clôture à l'autre", function () {
    $jauges = collect(['2024-04', '2025-05', '2026-03', '2026-07'])
        ->map(fn ($mois) => propsTableau($this->get(route('dashboard', ['mois' => $mois])))['tableau']['trajectoire']['jauge']);

    expect($jauges->pluck('seuil')->unique())->toHaveCount(1)
        ->and($jauges->pluck('zero')->unique()->all())->toBe([50])
        ->and($jauges->pluck('hausse')->every(fn ($p) => $p >= 0 && $p <= 100))->toBeTrue();
})->skip($sansFichier, $raison);

it('colore chaque point de la courbe selon le niveau de son mois', function () {
    $courbe = propsTableau($this->get(route('dashboard')))['tableau']['risque']['courbe'];
    $couleurs = array_combine(
        array_map(fn ($i) => now()->setDate(2023, 11, 1)->addMonthsNoOverflow($i)->format('Y-m'), array_keys($courbe['couleurs'])),
        $courbe['couleurs'],
    );
    $legende = collect($courbe['legende'])->pluck('couleur', 'libelle');

    expect($courbe['couleurs'])->toHaveCount(33)
        ->and($couleurs['2026-03'])->toBe($legende['Alerte'])
        ->and($couleurs['2026-02'])->toBe($legende['Vigilance'])
        ->and($couleurs['2026-07'])->toBe($legende['Normal ou non évalué'])
        ->and($couleurs['2024-01'])->toBe($legende['Normal ou non évalué'])
        ->and(collect($courbe['couleurs'])->countBy()->all())->toBe([
            $legende['Normal ou non évalué'] => 20,
            $legende['Alerte'] => 10,
            $legende['Vigilance'] => 3,
        ]);
})->skip($sansFichier, $raison);
