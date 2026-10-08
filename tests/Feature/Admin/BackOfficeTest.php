<?php

/*
 * Étape 5 : sections Imports, Journal et Paramètres du back-office, sur des
 * fichiers inventés fabriqués à la volée.
 */

use App\Enums\ModeCalcul;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\Generation;
use App\Models\Import;
use App\Registre\ServiceDecisions;
use App\Tableau\Format;
use App\Tableau\TableauDeBord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

/** Fichier envoyé comme par un navigateur : copie temporaire sans extension. */
function envoi(string $chemin, string $nom = 'donnees.xlsx'): UploadedFile
{
    $temporaire = tempnam(sys_get_temp_dir(), 'php');
    copy($chemin, $temporaire);

    return new UploadedFile($temporaire, $nom, null, null, true);
}

function flash(TestResponse $reponse, string $cle): mixed
{
    return $reponse->viewData('page')['flash'][$cle] ?? null;
}

it('refuse un fichier sans colonne par60 : erreurs affichées, rien de créé, refus journalisé', function () {
    $this->actingAs(analyste());

    $reponse = $this->followingRedirects()->post(route('admin.imports.store'), [
        'fichier' => envoi(FabriqueFichier::ecrire(FabriqueFichier::serie(), sansColonnes: ['par60']), 'sans-par60.xlsx'),
    ])->assertOk();

    $compteRendu = flash($reponse, 'compteRendu');
    expect($compteRendu['statut'])->toBe('refuse')
        ->and($compteRendu['fichier'])->toBe('sans-par60.xlsx')
        ->and($compteRendu['erreurs'])->toContain("Colonne obligatoire absente de l'onglet mensuel : par60.")
        ->and(Cloture::count())->toBe(0)
        ->and(Generation::count())->toBe(0)
        ->and(Import::count())->toBe(0)
        ->and(Storage::disk(ServiceImport::DISQUE_ARCHIVE)->allFiles())->toBe([]);

    $refus = Activity::query()->where('event', 'refus')->sole();
    expect($refus->causer_id)->toBe(analyste()->id)
        ->and($refus->properties['fichier'])->toBe('sans-par60.xlsx')
        ->and($refus->properties['erreurs'])->toContain("Colonne obligatoire absente de l'onglet mensuel : par60.");

    $journal = $this->get(route('admin.journal'))->viewData('page')['props']['entrees'];
    expect($journal[0]['action'])->toBe('Fichier refusé')
        ->and($journal[0]['qui'])->toBe(analyste()->name);
});

it('ajoute un mois nouveau depuis l\'écran, à la clôture, et met à jour la vérification de t−3', function () {
    $serie = FabriqueFichier::serie('2024-01', 21);
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(array_slice($serie, 0, 20, true)), analyste());
    expect(Cloture::query()->whereDate('mois', '2025-06-01')->value('hausse_reelle_3m'))->toBeNull();
    $this->actingAs(analyste());

    $reponse = $this->followingRedirects()->post(route('admin.imports.store'), ['fichier' => envoi(FabriqueFichier::ecrire($serie))])->assertOk();

    $nouveau = Cloture::query()->whereDate('mois', '2025-09-01')->sole();
    $compteRendu = flash($reponse, 'compteRendu');
    expect(Cloture::count())->toBe(21)
        ->and($nouveau->mode_calcul)->toBe(ModeCalcul::A_LA_CLOTURE)
        ->and(Cloture::query()->whereDate('mois', '2025-06-01')->value('hausse_reelle_3m'))->not->toBeNull()
        ->and($compteRendu['statut'])->toBe('importe')
        ->and($compteRendu['mode'])->toBe('Calcul à la clôture')
        ->and($compteRendu['mois'])->toBe([[
            'mois' => 'septembre 2025',
            'niveau' => $nouveau->niveau->libelle(),
            'couleur' => TableauDeBord::COULEURS[$nouveau->niveau->value],
        ]]);

    $historique = $reponse->viewData('page')['props']['imports'];
    expect($historique)->toHaveCount(2)
        ->and($historique[0]['utilisateur'])->toBe(analyste()->name)
        ->and($historique[0]['mois'])->toBe('septembre 2025 (1 mois)')
        ->and($historique[0]['etat'])->toBe('Actif')
        ->and($historique[1]['mois'])->toBe('janvier 2024 à août 2025 (20 mois)');
});

it("annule le dernier import depuis l'écran, ou explique le refus", function () {
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)), analyste());
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 8)), analyste());
    app(ServiceDecisions::class)->ajouter(Cloture::query()->whereDate('mois', '2024-07-01')->sole(), [
        'action' => 'Revue', 'responsable' => 'Crédit', 'echeance' => '2024-09-30',
    ], direction());
    $this->actingAs(analyste());

    $refus = flash($this->followingRedirects()->post(route('admin.imports.annuler')), 'annulation');
    expect($refus['statut'])->toBe('refuse')
        ->and($refus['message'])->toContain('Annulation refusée')
        ->and(Cloture::count())->toBe(8);

    Decision::query()->update(['cloture_id' => Cloture::query()->whereDate('mois', '2024-01-01')->value('id')]);
    $reponse = $this->followingRedirects()->post(route('admin.imports.annuler'));

    expect(flash($reponse, 'annulation')['statut'])->toBe('annule')
        ->and(Cloture::count())->toBe(6)
        ->and($reponse->viewData('page')['props']['imports'][0]['etat'])->toStartWith('Annulé le ');
});

it('corrige le résultat d\'une décision complétée et garde l\'ancien dans le journal', function () {
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)), analyste());
    $this->actingAs(direction());
    $this->post(route('decisions.store', ['mois' => '2024-06']), ['action' => 'Revue', 'responsable' => 'Crédit', 'echeance' => '30/09/2024']);
    $decision = Decision::sole();

    $this->patch(route('decisions.corriger', $decision), ['resultat' => 'Trop tôt'])
        ->assertSessionHasErrors(['resultat' => "Seul le résultat d'une décision complétée peut être corrigé."]);
    $this->patch(route('decisions.completer', $decision), ['resultat' => 'Dix dossiers revus']);
    $this->patch(route('decisions.corriger', $decision), ['resultat' => 'Douze dossiers revus'])
        ->assertRedirect(route('dashboard', ['mois' => '2024-06']));

    $correction = Activity::query()->where('event', 'correction')->sole();
    expect($decision->refresh()->resultat)->toBe('Douze dossiers revus')
        ->and($correction->causer_id)->toBe(direction()->id)
        ->and($correction->properties['ancien_resultat'])->toBe('Dix dossiers revus')
        ->and($correction->properties['nouveau_resultat'])->toBe('Douze dossiers revus')
        ->and($correction->properties['mois'])->toBe('2024-06');

    $journal = collect($this->get(route('admin.journal'))->viewData('page')['props']['entrees']);
    expect($journal->first()['action'])->toBe('Résultat corrigé')
        ->and($journal->first()['mois'])->toBe('juin 2024')
        ->and($journal->first()['detail'])->toContain('Ancien résultat : « Dix dossiers revus »');
});

it('liste le journal du plus récent au plus ancien, avec qui, quand, quoi et quel mois', function () {
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)), analyste());
    app(ServiceDecisions::class)->ajouter(Cloture::query()->whereDate('mois', '2024-05-01')->sole(), [
        'action' => 'Revue', 'responsable' => 'Crédit', 'echeance' => '2024-09-30',
    ], direction());
    $this->actingAs(analyste());

    $entrees = $this->get(route('admin.journal'))->assertOk()->viewData('page')['props']['entrees'];
    expect($entrees)->toHaveCount(2)
        ->and($entrees[0]['action'])->toBe('Décision ajoutée')
        ->and($entrees[0]['qui'])->toBe(direction()->name)
        ->and($entrees[0]['mois'])->toBe('mai 2024')
        ->and($entrees[0]['date'])->toMatch('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#')
        ->and($entrees[0]['detail'])->toContain('échéance : 30/09/2024')
        ->and($entrees[1]['action'])->toBe('Import')
        ->and($entrees[1]['mois'])->toBe('janvier 2024 à juin 2024 (6 mois)');
});

it('affiche les paramètres en vigueur en lecture seule', function () {
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 6)), analyste());
    $this->actingAs(direction());

    $props = $this->get(route('admin.parametres'))->assertOk()->viewData('page')['props'];
    expect($props['enregistre'])->toBeTrue()
        ->and($props['institution'])->toBe(config('alerte.nom_institution'))
        ->and($props['dateEffet'])->toBe(now()->format('d/m/Y'))
        ->and(collect($props['parametres'])->pluck('valeur', 'libelle')->map(fn ($v) => str_replace("\u{00A0}", ' ', $v))->all())->toBe([
            'Limite de niveau du PAR90' => '5,00 %',
            'Seuil de hausse sur trois mois' => '0,50 point',
            'Centile du bond' => '70e centile',
            'Nombre minimum de bonds passés' => '12 bonds',
            'Horizon de vérification' => '3 mois',
        ]);

    $this->post(route('admin.parametres'))->assertMethodNotAllowed();
    $this->put(route('admin.parametres'))->assertMethodNotAllowed();
});

it("affiche les horodatages dans le fuseau de l'institution", function () {
    config(['alerte.fuseau_horaire' => 'Indian/Antananarivo']);
    $utc = Carbon::create(2026, 10, 8, 17, 36, 0, 'UTC');

    expect(Format::horodatage($utc))->toBe('08/10/2026 20:36')
        ->and(Format::horodatage($utc->copy()->setTime(22, 30), false))->toBe('09/10/2026');
});
