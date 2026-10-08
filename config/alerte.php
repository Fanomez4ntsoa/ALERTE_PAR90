<?php

/*
|--------------------------------------------------------------------------
| Paramètres du calcul d'alerte PAR90
|--------------------------------------------------------------------------
|
| Cahier des charges, section 5 « Paramètres en configuration ».
| Aucune de ces valeurs n'est écrite ailleurs dans le code.
| Ce fichier ne doit contenir que des valeurs littérales : les tests du
| moteur le lisent directement, sans démarrer Laravel.
|
*/

return [

    // Limite de niveau du PAR90, en % : au-delà, statut « Hors limite ».
    'limite_par90' => 5,

    // Seuil de hausse du PAR90 sur trois mois, en points : à partir de là, « Alerte ».
    'seuil_hausse_3m' => 0.5,

    // Centile des bonds passés qui sert de seuil au signal avancé (entier, 1 à 100).
    'centile_bond' => 70,

    // Nombre minimum de bonds passés pour calculer un seuil.
    'minimum_bonds' => 12,

    // Horizon de la vérification a posteriori, en mois.
    'horizon_verification' => 3,

];
