<?php

namespace App\Http;

use App\Models\Parametre;
use Throwable;

/** Textes des pages d'erreur, en français. */
final class PageErreur
{
    private const TEXTES = [
        403 => ['Accès refusé', 'Votre rôle ne donne pas accès à cette page ou à cette action.'],
        404 => ['Page introuvable', 'Cette adresse ne correspond à aucune page, ou ce mois n\'a pas de clôture importée.'],
        419 => ['Session expirée', 'La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.'],
        429 => ['Trop de demandes', 'Patientez une minute avant de réessayer.'],
        500 => ['Erreur du serveur', 'L\'opération n\'a pas abouti. Réessayez ; si l\'erreur persiste, prévenez l\'analyste.'],
        503 => ['Service indisponible', 'L\'outil est momentanément indisponible. Réessayez dans quelques minutes.'],
    ];

    /** @return array<string, mixed> */
    public static function pour(int $statut): array
    {
        [$titre, $message] = self::TEXTES[$statut] ?? self::TEXTES[500];

        return [
            'statut' => $statut,
            'titre' => $titre,
            'message' => $message,
            'institution' => self::institution(),
            'urlTableau' => route('dashboard'),
        ];
    }

    private static function institution(): string
    {
        try {
            return Parametre::nomInstitution();
        } catch (Throwable) {
            return (string) config('alerte.nom_institution');
        }
    }
}
