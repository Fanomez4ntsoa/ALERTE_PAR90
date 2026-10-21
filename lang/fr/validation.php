<?php

/*
 * Messages de validation en français pour les formulaires du kit (profil,
 * mot de passe, connexion). Les autres règles retombent sur l'anglais.
 */
return [
    'accepted' => 'Le champ « :attribute » doit être accepté.',
    'confirmed' => 'La confirmation du champ « :attribute » ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ « :attribute » doit être une date valide.',
    'date_format' => 'Le champ « :attribute » doit être une date au format :format.',
    'email' => 'Le champ « :attribute » doit être une adresse e-mail valide.',
    'exists' => 'La valeur du champ « :attribute » est inconnue.',
    'extensions' => 'Le fichier doit avoir l\'une des extensions suivantes : :values.',
    'file' => 'Le champ « :attribute » doit être un fichier.',
    'in' => 'La valeur du champ « :attribute » n\'est pas valable.',
    'integer' => 'Le champ « :attribute » doit être un nombre entier.',
    'lowercase' => 'Le champ « :attribute » doit être en minuscules.',
    'max' => [
        'array' => 'Le champ « :attribute » ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier ne doit pas dépasser :max kilo-octets.',
        'numeric' => 'Le champ « :attribute » ne doit pas dépasser :max.',
        'string' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ « :attribute » doit contenir au moins :min éléments.',
        'file' => 'Le fichier doit faire au moins :min kilo-octets.',
        'numeric' => 'Le champ « :attribute » doit valoir au moins :min.',
        'string' => 'Le champ « :attribute » doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ « :attribute » doit être un nombre.',
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données : choisissez-en un autre.',
    ],
    'regex' => 'Le format du champ « :attribute » n\'est pas valable.',
    'required' => 'Le champ « :attribute » est obligatoire.',
    'string' => 'Le champ « :attribute » doit être un texte.',
    'unique' => 'Cette valeur du champ « :attribute » est déjà utilisée.',
    'uploaded' => 'Le fichier n\'a pas pu être envoyé.',

    'attributes' => [
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
        'password_confirmation' => 'confirmation du mot de passe',
        'fichier' => 'fichier',
    ],
];
