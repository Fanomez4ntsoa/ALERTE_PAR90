<?php

namespace App\Import;

use RuntimeException;

/** Contrôle bloquant : le fichier entier est refusé, rien n'est écrit. */
class FichierRefuse extends RuntimeException
{
    /**
     * @param  list<string>  $erreurs
     */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct('Fichier refusé : '.count($erreurs).' erreur(s) bloquante(s). '.implode(' ', array_slice($erreurs, 0, 5)));
    }
}
