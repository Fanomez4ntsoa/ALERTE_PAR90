<?php

namespace App\Registre;

use App\Enums\EtatDecision;
use App\Models\Cloture;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registre des décisions (section 9) : ajout, complément, annulation.
 * Chaque opération est journalisée ; une décision ne se supprime jamais.
 */
class ServiceDecisions
{
    /**
     * @param  array{action: string, responsable: string, echeance: string}  $donnees
     */
    public function ajouter(Cloture $cloture, array $donnees, User $auteur): Decision
    {
        return DB::transaction(function () use ($cloture, $donnees, $auteur) {
            $decision = $cloture->decisions()->create([
                ...$donnees,
                'etat' => EtatDecision::OUVERTE,
                'auteur_id' => $auteur->id,
            ]);
            $this->journaliser($decision, $auteur, 'ajout', 'Décision ajoutée', [
                'mois' => $cloture->mois->format('Y-m'),
                'action' => $decision->action,
                'responsable' => $decision->responsable,
                'echeance' => $decision->echeance->format('d/m/Y'),
            ]);

            return $decision;
        });
    }

    public function completer(Decision $decision, string $resultat, User $utilisateur): Decision
    {
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

    public function annuler(Decision $decision, string $motif, User $utilisateur): Decision
    {
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
            ->withProperties($proprietes)
            ->log($message);
    }
}
