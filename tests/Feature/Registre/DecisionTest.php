<?php

/*
 * Registre des décisions (section 9) sur des données inventées : ajout,
 * complément, annulation avec motif, jamais de suppression, journal,
 * bloc 6 et refus d'annuler un import qui porte une décision.
 */

use App\Enums\EtatDecision;
use App\Enums\EtatImport;
use App\Import\AnnulationRefusee;
use App\Import\ServiceImport;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FabriqueFichier;

beforeEach(function () {
    Storage::fake(ServiceImport::DISQUE_ARCHIVE);
    app(ServiceImport::class)->importer(FabriqueFichier::ecrire(FabriqueFichier::serie('2024-01', 8)), analyste());
    $this->direction = User::factory()->direction()->create(['name' => 'Direction']);
    $this->actingAs($this->direction);
});

afterEach(function () {
    FabriqueFichier::nettoyer();
});

function decisionValide(array $autres = []): array
{
    return [
        'action' => 'Revue des créances en 61–90 jours de l\'agence nord',
        'responsable' => 'Responsable crédit',
        'echeance' => '15/07/2024',
        ...$autres,
    ];
}

function registre(string $mois): array
{
    return test()->get(route('dashboard', ['mois' => $mois]))->viewData('page')['props']['tableau']['registre'];
}

it('inscrit plusieurs décisions sur un mois, quel que soit son niveau', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide())
        ->assertRedirect(route('dashboard', ['mois' => '2024-06']));
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide(['action' => 'Deuxième décision']));
    $this->post(route('decisions.store', ['mois' => '2024-01']), decisionValide(['action' => 'Décision sur un mois Non évalué']));

    $decisions = registre('2024-06')['decisions'];
    expect($decisions)->toHaveCount(2)
        ->and($decisions[0]['etat'])->toBe('ouverte')
        ->and($decisions[0]['libelleEtat'])->toBe('Ouverte')
        ->and($decisions[0]['echeance'])->toBe('15/07/2024')
        ->and($decisions[0]['saisie'])->toContain('par Direction')
        ->and($decisions[0]['urlCompleter'])->not->toBeNull()
        ->and(registre('2024-01')['decisions'])->toHaveCount(1);
});

it('refuse une décision incomplète ou sur un mois absent', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), ['action' => '', 'responsable' => '', 'echeance' => 'demain'])
        ->assertSessionHasErrors(['action', 'responsable', 'echeance']);
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide(['echeance' => '2024-07-15']))
        ->assertSessionHasErrors(['echeance' => 'Le champ « échéance » doit être une date au format jj/mm/aaaa.']);
    $this->post(route('decisions.store', ['mois' => '2030-01']), decisionValide())->assertNotFound();

    expect(Decision::count())->toBe(0);
});

it('complète une décision avec son résultat', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    $decision = Decision::sole();

    $this->patch(route('decisions.completer', $decision), ['resultat' => ''])->assertSessionHasErrors('resultat');
    $this->patch(route('decisions.completer', $decision), ['resultat' => 'Douze dossiers restructurés'])
        ->assertRedirect(route('dashboard', ['mois' => '2024-06']));

    $affichee = registre('2024-06')['decisions'][0];
    expect($decision->refresh()->etat)->toBe(EtatDecision::COMPLETEE)
        ->and($decision->complete_par)->toBe($this->direction->id)
        ->and($affichee['resultat'])->toBe('Douze dossiers restructurés')
        ->and($affichee['complement'])->toContain('par Direction')
        ->and($affichee['urlCompleter'])->toBeNull()
        ->and($affichee['urlAnnuler'])->toBeNull();
});

it('garde visible une décision annulée, avec son motif obligatoire', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    $decision = Decision::sole();

    $this->patch(route('decisions.annuler', $decision), ['motif' => ''])
        ->assertSessionHasErrors(['motif' => "Le champ « motif d'annulation » est obligatoire."]);
    expect($decision->refresh()->etat)->toBe(EtatDecision::OUVERTE);

    $this->patch(route('decisions.annuler', $decision), ['motif' => 'Doublon de la décision du comité']);

    $affichee = registre('2024-06')['decisions'][0];
    expect(Decision::count())->toBe(1)
        ->and($affichee['etat'])->toBe('annulee')
        ->and($affichee['libelleEtat'])->toBe('Annulée')
        ->and($affichee['action'])->toBe(decisionValide()['action'])
        ->and($affichee['motifAnnulation'])->toBe('Doublon de la décision du comité')
        ->and($affichee['annulation'])->toContain('par Direction');

    // Une décision annulée ne se complète plus et ne se ré-annule pas.
    $this->patch(route('decisions.completer', $decision), ['resultat' => 'Trop tard'])->assertSessionHasErrors('resultat');
    $this->patch(route('decisions.annuler', $decision), ['motif' => 'Encore'])->assertSessionHasErrors('motif');
    expect($decision->refresh()->resultat)->toBeNull();
});

it('ne supprime jamais une décision', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());

    expect(fn () => Decision::sole()->delete())->toThrow(LogicException::class);
    expect(Decision::count())->toBe(1);
});

it('journalise chaque ajout, complément et annulation', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide(['action' => 'Seconde']));
    [$premiere, $seconde] = Decision::query()->orderBy('id')->get();
    $this->patch(route('decisions.completer', $premiere), ['resultat' => 'Fait']);
    $this->patch(route('decisions.annuler', $seconde), ['motif' => 'Erreur de saisie']);

    $journal = Activity::query()->where('log_name', 'decision')->orderBy('id')->get();
    expect($journal->pluck('event')->all())->toBe(['ajout', 'ajout', 'complement', 'annulation'])
        ->and($journal->every(fn ($a) => $a->causer_id === $this->direction->id))->toBeTrue()
        ->and($journal[2]->subject_id)->toBe($premiere->id)
        ->and($journal[3]->properties['motif'])->toBe('Erreur de saisie')
        ->and($journal[0]->properties['mois'])->toBe('2024-06');
});

it('montre en bloc 6 le signal de t−3 et les décisions prises à l\'époque', function () {
    $this->post(route('decisions.store', ['mois' => '2024-03']), decisionValide(['action' => 'Décision de mars']));
    $avant = Cloture::query()->whereDate('mois', '2024-03-01')->first();

    $registre = registre('2024-06');
    expect($registre['moisSignal'])->toBe('mars 2024')
        ->and($registre['signal']['mois'])->toBe('mars 2024')
        ->and($registre['signal']['niveau'])->toBe($avant->niveau->libelle())
        ->and($registre['signal']['decisions'])->toHaveCount(1)
        ->and($registre['signal']['decisions'][0]['action'])->toBe('Décision de mars')
        // En consultation seulement : on agit sur une décision depuis son propre mois.
        ->and($registre['signal']['decisions'][0]['urlCompleter'])->toBeNull()
        ->and($registre['decisions'])->toBe([]);

    expect(registre('2024-02')['signal'])->toBeNull();
});

it('affiche « Sans objet » pour un mois Non évalué et « En attente » sans t+3', function () {
    $sansObjet = registre('2024-04')['signal'];
    $lignes = collect($this->get(route('recapitulatif'))->viewData('page')['props']['lignes'])->keyBy('mois');

    expect($sansObjet['mois'])->toBe('janvier 2024')
        ->and($sansObjet['niveau'])->toBe('Non évalué')
        ->and($sansObjet['resultat'])->toBe('Sans objet')
        ->and($lignes['janvier 2024']['resultat'])->toBe('Sans objet')
        ->and($lignes['août 2024']['resultat'])->toBe('En attente');
});

it('compte les décisions par mois dans le récapitulatif', function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    $this->patch(route('decisions.annuler', Decision::query()->orderBy('id')->first()), ['motif' => 'Doublon']);

    $ligne = collect($this->get(route('recapitulatif'))->viewData('page')['props']['lignes'])->firstWhere('mois', 'juin 2024');
    expect($ligne['decisions'])->toBe(2)
        ->and($ligne['decisionsAnnulees'])->toBe('(dont 1 annulée)');
});

it("refuse d'annuler un import quand une décision existe sur l'un de ses mois", function () {
    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide());
    // Même annulée, la décision reste inscrite au registre et bloque l'annulation.
    $this->patch(route('decisions.annuler', Decision::sole()), ['motif' => 'Doublon']);

    expect(fn () => app(ServiceImport::class)->annulerDernier(analyste()))
        ->toThrow(AnnulationRefusee::class, 'Annulation refusée : 1 décision(s) inscrite(s) au registre pour 2024-06.');

    $this->artisan('alerte:annuler-import', ['--utilisateur' => analyste()->email, '--force' => true])
        ->expectsOutputToContain('Annulation refusée')
        ->assertFailed();

    expect(Cloture::count())->toBe(8)
        ->and(Import::sole()->etat)->toBe(EtatImport::ACTIF)
        ->and(Decision::count())->toBe(1);
});

it('réserve le registre aux utilisateurs connectés', function () {
    auth()->logout();

    $this->post(route('decisions.store', ['mois' => '2024-06']), decisionValide())->assertRedirect(route('login'));
    $this->get(route('recapitulatif'))->assertRedirect(route('login'));
    expect(Decision::count())->toBe(0);
});
