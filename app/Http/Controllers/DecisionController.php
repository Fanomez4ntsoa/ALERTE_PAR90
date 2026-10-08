<?php

namespace App\Http\Controllers;

use App\Models\Cloture;
use App\Models\Decision;
use App\Registre\ServiceDecisions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Registre des décisions (section 9). Pas encore de rôles : tout utilisateur connecté saisit. */
class DecisionController extends Controller
{
    /** Messages en français : le projet n'installe pas de paquet de traductions. */
    private const MESSAGES = [
        'required' => 'Le champ « :attribute » est obligatoire.',
        'string' => 'Le champ « :attribute » doit être un texte.',
        'max' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
        'date_format' => 'Le champ « :attribute » doit être une date au format jj/mm/aaaa.',
    ];

    public function __construct(private readonly ServiceDecisions $service) {}

    public function store(Request $request, string $mois): RedirectResponse
    {
        $cloture = Cloture::query()->whereDate('mois', "{$mois}-01")->firstOrFail();
        $donnees = $request->validate([
            'action' => ['required', 'string', 'max:2000'],
            'responsable' => ['required', 'string', 'max:255'],
            'echeance' => ['required', 'date_format:d/m/Y'],
        ], self::MESSAGES, ['action' => 'action décidée', 'echeance' => 'échéance']);
        // Saisie au format jj/mm/aaaa, stockée en date.
        $donnees['echeance'] = Carbon::createFromFormat('!d/m/Y', $donnees['echeance'])->format('Y-m-d');

        $this->service->ajouter($cloture, $donnees, $request->user());

        return to_route('dashboard', ['mois' => $mois]);
    }

    public function completer(Request $request, Decision $decision): RedirectResponse
    {
        $donnees = $request->validate(['resultat' => ['required', 'string', 'max:2000']], self::MESSAGES, ['resultat' => 'résultat']);
        $this->service->completer($decision, $donnees['resultat'], $request->user());

        return to_route('dashboard', ['mois' => $decision->cloture->mois->format('Y-m')]);
    }

    public function annuler(Request $request, Decision $decision): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:2000']], self::MESSAGES, ['motif' => "motif d'annulation"]);
        $this->service->annuler($decision, $donnees['motif'], $request->user());

        return to_route('dashboard', ['mois' => $decision->cloture->mois->format('Y-m')]);
    }
}
