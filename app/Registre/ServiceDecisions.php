<?php

namespace App\Registre;

use App\Enums\EtatDecision;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\User;
use App\Tableau\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Registre des décisions (section 9) : ajout, complément, correction, annulation.
 * Réservé à la direction (section 11). Chaque opération est journalisée avec
 * le mois concerné ; une décision ne se supprime jamais.
 */
class ServiceDecisions
{
    /**
     * @param  array{action: string, responsable: string, echeance: string}  $donnees
     */
    public function ajouter(Cloture $cloture, array $donnees, User $auteur): Decision
    {
        Gate::forUser($auteur)->authorize('create', Decision::class);

        return DB::transaction(function () use ($cloture, $donnees, $auteur) {
            $decision = $cloture->decisions()->create([
                ...$donnees,
                'etat' => EtatDecision::OUVERTE,
                'auteur_id' => $auteur->id,
            ]);
            $this->journaliser($decision, $auteur, 'ajout', 'Décision ajoutée', [
                'action' => $decision->action,
                'responsable' => $decision->responsable,
                'echeance' => Format::date($decision->echeance),
            ]);

            return $decision;
        });
    }

    public function completer(Decision $decision, string $resultat, User $utilisateur): Decision
    {
        Gate::forUser($utilisateur)->authorize('completer', $decision);
        $this->exigerOuverte($decision, 'resultat');

        return DB::transaction(function () use ($decision, $resultat, $utilisateur) {
            $decision->update([
                'resultat' => $resultat,
                'etat' => EtatDecision::COMPLETEE,
                'complete_par' => $utilisateur->id,
                'complete_le' => Carbon::now(),
            ]);
            $this->journaliser($decision, $utilisateur, 'complement', 'Décision complétée', ['resultat' => $resultat]);

            return $decision;
        });
    }

    /** Corrige le résultat d'une décision complétée ; l'ancienne valeur reste dans le journal. */
    public function corriger(Decision $decision, string $resultat, User $utilisateur): Decision
    {
        Gate::forUser($utilisateur)->authorize('corriger', $decision);
        if ($decision->etat !== EtatDecision::COMPLETEE) {
            throw ValidationException::withMessages([
                'resultat' => 'Seul le résultat d\'une décision complétée peut être corrigé.',
            ]);
        }

        return DB::transaction(function () use ($decision, $resultat, $utilisateur) {
            $ancien = $decision->resultat;
            $decision->update(['resultat' => $resultat]);
            $this->journaliser($decision, $utilisateur, 'correction', 'Résultat de décision corrigé', [
                'ancien_resultat' => $ancien,
                'nouveau_resultat' => $resultat,
            ]);

            return $decision;
        });
    }

    public function annuler(Decision $decision, string $motif, User $utilisateur): Decision
    {
        Gate::forUser($utilisateur)->authorize('annuler', $decision);
        $this->exigerOuverte($decision, 'motif');

        return DB::transaction(function () use ($decision, $motif, $utilisateur) {
            $decision->update([
                'motif_annulation' => $motif,
                'etat' => EtatDecision::ANNULEE,
                'annule_par' => $utilisateur->id,
                'annule_le' => Carbon::now(),
            ]);
            $this->journaliser($decision, $utilisateur, 'annulation', 'Décision annulée', ['motif' => $motif]);

            return $decision;
        });
    }

    private function exigerOuverte(Decision $decision, string $champ): void
    {
        if ($decision->etat !== EtatDecision::OUVERTE) {
            throw ValidationException::withMessages([
                $champ => "Cette décision est déjà {$decision->etat->libelle()} : elle ne peut plus changer.",
            ]);
        }
    }

    /** @param  array<string, mixed>  $proprietes */
    private function journaliser(Decision $decision, User $utilisateur, string $evenement, string $message, array $proprietes): void
    {
        activity('decision')
            ->event($evenement)
            ->performedOn($decision)
            ->causedBy($utilisateur)
            ->withProperties(['mois' => $decision->cloture->mois->format('Y-m'), ...$proprietes])
            ->log($message);
    }
}
