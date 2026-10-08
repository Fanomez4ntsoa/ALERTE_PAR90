<?php

namespace App\Domain\Alerte;

use InvalidArgumentException;

/**
 * Les cinq paramètres du calcul (cahier des charges, section 5).
 * Aucune valeur par défaut ici : elles viennent de config/alerte.php.
 */
final readonly class Parametres
{
    public function __construct(
        public float $limitePar90,
        public float $seuilHausse3m,
        public int $centileBond,
        public int $minimumBonds,
        public int $horizonVerification,
    ) {
        if ($centileBond < 1 || $centileBond > 100) {
            throw new InvalidArgumentException("Centile du bond hors de 1–100 : {$centileBond}.");
        }
        if ($minimumBonds < 2) {
            throw new InvalidArgumentException("Le minimum de bonds doit être au moins 2 : {$minimumBonds}.");
        }
        if ($horizonVerification < 1) {
            throw new InvalidArgumentException("Horizon de vérification invalide : {$horizonVerification}.");
        }
    }

    /**
     * @param  array<string, mixed>  $config  contenu de config/alerte.php
     */
    public static function depuisConfig(array $config): self
    {
        $cles = ['limite_par90', 'seuil_hausse_3m', 'centile_bond', 'minimum_bonds', 'horizon_verification'];

        foreach ($cles as $cle) {
            if (! isset($config[$cle]) || ! is_numeric($config[$cle])) {
                throw new InvalidArgumentException("Paramètre absent ou non numérique : {$cle}.");
            }
        }

        foreach (['centile_bond', 'minimum_bonds', 'horizon_verification'] as $cle) {
            if ((float) $config[$cle] !== floor((float) $config[$cle])) {
                throw new InvalidArgumentException("Le paramètre {$cle} doit être un entier.");
            }
        }

        return new self(
            limitePar90: (float) $config['limite_par90'],
            seuilHausse3m: (float) $config['seuil_hausse_3m'],
            centileBond: (int) $config['centile_bond'],
            minimumBonds: (int) $config['minimum_bonds'],
            horizonVerification: (int) $config['horizon_verification'],
        );
    }
}
