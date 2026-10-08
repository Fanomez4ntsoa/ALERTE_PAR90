# Cahier des charges v2 — Outil d'alerte précoce PAR90

Oct 8, 2026 · @Fanomezantsoa

## 1. Objet et périmètre

La v1 est un tableau de bord mensuel d'une page, prêt pour la soutenance : il charge les 33 mois de données du mémoire, calcule le niveau d'alerte de chaque mois et affiche l'écran décrit en annexe H.

L'outil ne prédit rien et ne décide rien. Il calcule avec des règles fixes, affiche, et garde la trace des décisions prises par la direction.

| Dans la v1 (soutenance) | Reporté après la soutenance |
| --- | --- |
| Import du fichier modèle (33 mois, puis un mois à la fois) | Lecture directe des 7 onglets bruts de Looker Studio |
| Contrôle des données avant calcul | Plusieurs institutions dans la même application |
| Calcul des indicateurs, du seuil et du niveau | Écran de réglage des seuils (ils sont déjà en configuration) |
| Page du tableau de bord, navigable mois par mois | Règle d'alerte sur les générations récentes (non fixée dans le mémoire) |
| Registre des décisions et vérification à trois mois | Envoi d'e-mails, export PDF |
| Connexion avec deux rôles : analyste, direction | Récapitulatif de pilote détaillé |

Ce document remplace « Explication développé » et « Explication simple ». En cas d'écart, c'est lui qui fait foi, puis le chapitre 4 du mémoire.

## 2. Ce qui change par rapport à la version précédente

Les règles de calcul ne changent pas : rejouées sur `donnees_chap3.xlsx`, elles redonnent le tableau I.1 du mémoire. Les neuf changements comblent des points que le développeur aurait dû deviner.

| N° | Changement | Pourquoi | Section |
| --- | --- | --- | --- |
| 1 | Format du fichier d'import fixé colonne par colonne | « CSV ou Excel » ne suffit pas pour écrire un import fiable | 4 |
| 2 | Date du taux de passage : règle explicite de décalage d'un mois | Un décalage erroné fausse l'indicateur sans provoquer d'erreur | 3, 5 |
| 3 | Retards de plus de 180 et 365 jours acceptés en taux | Le fichier de données ne contient pas leurs montants | 4, 5 |
| 4 | Historique minimum fixé à 12 bonds, en configuration | Le mémoire cite 12 et 18 mois selon l'endroit | 3, 5 |
| 5 | Vérification à trois mois calculée pour tous les mois, séparée des décisions | Sans elle, impossible de compter les hausses manquées ; un mois peut avoir plusieurs décisions | 9, 10 |
| 6 | Six décimales en base, comparaison avant arrondi | Deux mois sont à 0,02 et 0,04 point du seuil d'alerte | 5 |
| 7 | Annulation d'un import possible, avec trace | Un mauvais fichier sera chargé un jour | 7 |
| 8 | Règles pour mois manquant et valeur manquante | Sinon l'outil calcule un écart entre deux mois non consécutifs | 7 |
| 9 | « Fausse alerte » remplacé par un libellé propre à chaque niveau | Le niveau Alerte constate une hausse, il ne l'annonce pas | 9 |

## 3. Points à confirmer

Aucun de ces points ne bloque le développement : chacun a une valeur par défaut, placée en configuration et modifiable sans toucher au code.

| Question | À qui | Valeur retenue par défaut |
| --- | --- | --- |
| Dans le fichier, le taux de passage de la ligne « juillet » est déjà rempli alors qu'il ne devrait être connu que fin août. Quelle est la vraie date ? | Analyste | La ligne du mois t décrit le passage de fin t à fin t+1. L'outil affiche donc en t la valeur de la ligne t−1 (juillet 2026 affiche 73,44 %, comme l'annexe H). |
| Historique minimum pour le signal avancé : 12 ou 18 mois ? | Auteure du mémoire | 12 bonds passés. Conséquence : une Vigilance apparaît en mars 2025, hors des 12 dates testées. |
| Le seuil du bond inclut-il le mois en cours ? | Auteure du mémoire | Non, bonds strictement antérieurs. Les deux versions donnent les mêmes niveaux sur les 33 mois. |
| Le taux de passage est-il calculé en montant ou en nombre de prêts ? | Analyste | Sans effet sur l'outil : la valeur est affichée telle quelle, sans seuil. |
| Quel nom d'institution afficher à l'écran ? | Auteure du mémoire | Un nom en configuration, « Institution » par défaut. |
| Date de la soutenance | Auteure du mémoire | Non renseignée : elle fixe le calendrier de la section 13. |

## 4. Données en entrée

L'outil importe un fichier Excel qui a exactement la structure de `donnees_chap3.xlsx`. L'analyste le complète chaque mois à partir du tableau de bord opérationnel.

### Onglets lus

| Onglet | Une ligne par | Usage |
| --- | --- | --- |
| `mensuel` | mois de clôture | Tous les calculs |
| `vintage_par30` | mois d'octroi (colonnes `age_0` à `age_12`) | Tableau des générations récentes |
| `vintage_par90` | mois d'octroi (colonnes `age_0` à `age_12`) | Stocké, non affiché en v1 |

Les onglets `rollrate_matrice` et `dictionnaire` sont ignorés.

### Colonnes utilisées de l'onglet `mensuel`

| Colonne | Contenu | Unité | Obligatoire |
| --- | --- | --- | --- |
| `Mois` | Premier jour du mois de clôture | date | Oui |
| `encours` | Encours capital fin de mois | ariary | Oui |
| `par30`, `par60`, `par90` | Part de l'encours en retard de plus de 30, 60, 90 jours | % | Oui |
| `par180`, `par365` | Idem, plus de 180 et 365 jours | % | Non |
| `par90_mt` | Montant en retard de plus de 90 jours | ariary | Oui |
| `impaye` | Taux d'impayés | % | Non |
| `radiations` | Créances radiées dans le mois ; vide = non renseigné | ariary | Non |
| `rr_61_90` | Taux de passage de la tranche 61–90 jours vers plus de 90 jours | % | Non |

Les autres colonnes sont conservées dans le fichier archivé mais ne sont pas lues.

### Contrôles avant tout calcul

Un contrôle bloquant refuse le fichier entier ; un avertissement s'affiche à l'écran et l'import continue.

| Contrôle | Type |
| --- | --- |
| Onglet ou colonne obligatoire absent | Bloquant |
| `Mois` invalide, en double, ou postérieur à la date du jour | Bloquant |
| Valeur obligatoire vide ou non numérique | Bloquant |
| Taux hors de l'intervalle 0–100 | Bloquant |
| Ordre non respecté : `par30` ≥ `par60` ≥ `par90` ≥ `par180` ≥ `par365` | Bloquant |
| `par90_mt` s'écarte de plus de 1 % de `par90` × `encours` | Avertissement |
| `radiations` vide | Avertissement, affiché « n.r. » |
| Mois manquant entre deux mois importés | Avertissement |
| Mois déjà en base avec des valeurs différentes | Avertissement : la ligne en base n'est pas modifiée |

### Règles d'import

- Le fichier peut contenir tout l'historique : seuls les mois absents de la base sont ajoutés.
- Une cellule vide est enregistrée comme absente, jamais comme zéro.
- Dans les onglets de générations, toute cellule dont le mois d'octroi plus l'âge dépasse le dernier mois de clôture est écartée, même si elle vaut zéro.
- Le fichier d'origine est archivé avec la date, l'utilisateur et la liste des avertissements.

## 5. Règles de calcul

Tous les calculs sont des soustractions, des divisions et un tri. Ils se font sur les valeurs brutes ; l'arrondi n'intervient qu'à l'affichage.

### Indicateurs calculés pour le mois t

| Indicateur | Formule | Unité |
| --- | --- | --- |
| Tranche 61–90 | `par60(t) − par90(t)` | point |
| Bond de la tranche | `tranche(t) − tranche(t−1)` | point |
| Hausse sur trois mois | `par90(t) − par90(t−h)`, h = horizon de vérification (3 mois) | point |
| Part des retards de plus de 180 jours | `par180(t) ÷ par90(t) × 100` | % |
| Part des retards de plus d'un an | `par365(t) ÷ par90(t) × 100` | % |
| Taux de passage connu | `rr_61_90` de la ligne t−1 | % |
| Hausse réelle après trois mois | `par90(t+h) − par90(t)`, calculée quand t+h est importé | point |

### Seuil du bond

Le seuil du mois t est le 70e centile des bonds strictement antérieurs à t, par interpolation linéaire (méthode par défaut de R, dite type 7).

```
liste = bonds des mois antérieurs à t, triés par ordre croissant
n     = nombre de valeurs
si n < 12 : pas de seuil, signal avancé indisponible
rang  = 0,70 × (n − 1)            // index à partir de 0
bas   = partie entière de rang
seuil = liste[bas] + (rang − bas) × (liste[bas + 1] − liste[bas])
seuil = arrondi à 8 décimales
```

Le seuil est arrondi à 8 décimales ; c'est cette valeur arrondie qui est comparée au bond et stockée (`DECIMAL(12,8)`).

Le signal avancé est allumé si `bond(t) ≥ seuil(t)`. Pour chaque mois, l'outil enregistre le seuil et le nombre de bonds utilisés.

### Paramètres en configuration

| Paramètre | Valeur par défaut |
| --- | --- |
| Limite de niveau | 5 % |
| Seuil de hausse sur trois mois | 0,5 point |
| Centile du bond | 70 |
| Nombre minimum de bonds passés | 12 |
| Horizon de vérification | 3 mois — sert aussi de décalage à la hausse sur trois mois : c'est la même définition utilisée deux fois |

Aucune de ces valeurs n'est écrite dans le code.

### Précision

- Les taux sont stockés avec six décimales, le seuil du bond avec huit, les montants en ariary entiers.
- Toute comparaison à un seuil utilise la valeur stockée, jamais la valeur arrondie affichée. Le seuil du bond est arrondi à 8 décimales avant la comparaison.
- Affichage : deux décimales pour les taux et les écarts, trois pour le seuil du bond, une pour les parts des retards de plus de 180 jours et de plus d'un an dans le PAR90, montants en milliards d'ariary avec deux décimales.

## 6. Niveaux d'alerte et statut

Chaque mois reçoit deux informations indépendantes : un niveau, qui dit s'il faut ouvrir une revue, et un statut, qui situe le PAR90 par rapport à la limite de 5 %. Le statut ne modifie jamais le niveau.

### Niveau

Les conditions sont testées dans cet ordre ; la première vraie s'applique.

1. La hausse sur trois mois n'est pas calculable : **Non évalué**.
2. La hausse sur trois mois est supérieure ou égale à 0,5 point : **Alerte**.
3. Le seuil du bond existe et le bond lui est supérieur ou égal : **Vigilance**.
4. Sinon : **Normal**.

| Niveau | Couleur | Sens | Action affichée |
| --- | --- | --- | --- |
| Normal | Vert | Ni hausse constatée, ni signal avancé | Suivi mensuel habituel |
| Vigilance | Jaune | Un signal avancé s'allume avant la hausse | Revue ciblée par la direction avant la clôture suivante : créances en 61–90 jours, générations récentes |
| Alerte | Rouge | Une hausse est constatée et peut se prolonger | Revue prioritaire par la direction avant la clôture suivante : action, responsable, échéance |
| Non évalué | Gris | Historique insuffisant ou donnée manquante | Aucune |

Le motif est affiché avec le niveau, par exemple « Hausse du PAR90 de +0,52 point sur trois mois ».

### Statut

| Condition | Statut | Texte affiché |
| --- | --- | --- |
| `par90(t)` > 5 % | Hors limite | Le PAR90 dépasse la limite de 5 %. Point sur le plan de traitement du stock ancien. |
| Sinon | Dans la limite | Aucun |

Le niveau et le statut sont figés au moment de l'import, avec les paramètres alors en vigueur. Un changement de paramètre ne recalcule pas les mois déjà enregistrés.

## 7. Cas limites

Une donnée absente donne un résultat absent : l'outil n'invente jamais de valeur et ne calcule jamais par-dessus un trou.

| Situation | Comportement |
| --- | --- |
| Moins de 4 mois d'historique | Hausse sur trois mois non calculable ; niveau « Non évalué » |
| Moins de 12 bonds passés | Pas de seuil ; « Données insuffisantes pour le signal avancé » ; seuls Normal et Alerte sont possibles |
| Mois t−1 absent de la base | Bond du mois t absent ; pas de Vigilance possible ce mois-là |
| Mois t−3 absent de la base | Hausse sur trois mois absente ; niveau « Non évalué » |
| Bond absent dans l'historique | Il est simplement exclu de la liste qui sert au seuil |
| `radiations` vide | Affiché « n.r. », avec la mention « une baisse du PAR90 ce mois-ci ne peut pas être interprétée » |
| `par180`, `par365`, `impaye` ou `rr_61_90` vide | L'indicateur concerné affiche « n.r. » ; le niveau n'est pas touché |
| Mois t+3 pas encore importé | Vérification « En attente » |

### Chargement initial

Les 33 mois du mémoire sont chargés en une fois. Leurs niveaux sont calculés mois par mois, chacun avec les seuls mois antérieurs, et marqués « calcul rétrospectif ». Les mois importés ensuite sont marqués « calcul à la clôture ».

### Correction d'un import

- Une ligne importée ne se modifie pas à la main.
- L'analyste peut annuler le dernier import : ses mois sont retirés, ainsi que les calculs qui en dépendent.
- L'annulation est refusée si une décision est inscrite au registre pour l'un de ces mois, même une décision annulée. Le déblocage par la direction est reporté après la v1.
- Chaque import et chaque annulation sont tracés : qui, quand, quel fichier, quels mois.

## 8. Écran du tableau de bord

Une seule page, six blocs de haut en bas, chacun répondant à une question. Un sélecteur de mois permet d'afficher n'importe quelle clôture ; la page s'ouvre sur la plus récente.

| Bloc | Question | Contenu | Valeur attendue pour juillet 2026 |
| --- | --- | --- | --- |
| 1. Bandeau | Faut-il ouvrir une revue ? | Niveau coloré, motif, statut, action attendue | Normal, hors limite |
| 2. Niveau de risque | Où en est le risque ? | PAR90 en taux et en montant ; courbe de tout l'historique avec une ligne horizontale à 5 % | 6,25 % ; 12,79 Md Ar |
| 3. Trajectoire | Le PAR90 s'est-il dégradé sur trois mois ? | Hausse sur trois mois, comparée à +0,5 point | −0,25 point |
| 4. Signal avancé | Une hausse se prépare-t-elle ? | Bond du mois, seuil du mois, voyant allumé ou éteint, nombre de bonds utilisés | Bond −0,05 ; seuil 0,135 ; éteint ; 31 bonds |
| 5. Compréhension | Où regarder pendant la revue ? | Huit indicateurs sans seuil, voir ci-dessous | Voir ci-dessous |
| 6. Registre | Qu'a-t-on décidé, et avait-on raison ? | Décisions du mois ; suivi des signaux arrivés à échéance | Voir section 9 |

### Bloc 5 : indicateurs de compréhension

Chaque indicateur affiche la valeur du mois et celle d'il y a trois mois. Aucun ne déclenche de niveau.

| Indicateur | Juillet 2026 | Trois mois plus tôt (avril 2026) |
| --- | --- | --- |
| PAR30 | 8,04 % | 8,62 % |
| PAR60 | 6,97 % | 7,40 % |
| Taux d'impayés | 8,14 % | 8,10 % |
| Tranche 61–90 | 0,72 % | 0,91 % |
| Part des retards de plus de 180 jours dans le PAR90 | 78,5 % | 66,5 % |
| Part des retards de plus d'un an dans le PAR90 | 41,1 % | 35,8 % |
| Taux de passage connu | 73,44 % | 84,66 % |
| Radiations du mois | 0,47 Md Ar | n.r. |

Le bloc contient aussi le tableau des générations récentes : les 12 derniers mois d'octroi en lignes, l'âge en colonnes, le PAR30 dans les cellules. Il est affiché sans seuil ni couleur d'alerte.

### Règles d'affichage

- Le niveau est toujours écrit en toutes lettres : la couleur seule ne suffit pas.
- La page tient sur un écran d'ordinateur et s'imprime sur une feuille A4.
- Un mois calculé rétrospectivement porte la mention « calcul rétrospectif » dans le bandeau.

## 9. Registre et vérification à trois mois

Le registre réunit deux choses distinctes : ce que la direction décide, saisi à la main, et ce que le PAR90 a fait ensuite, calculé par l'outil.

### Décisions

- La direction peut inscrire une ou plusieurs décisions pour n'importe quel mois, quel que soit le niveau.
- Chaque décision comporte : action décidée, responsable, échéance, et plus tard un résultat en texte libre.
- Une décision enregistrée ne se supprime pas ; elle se complète ou s'annule avec un motif.
- L'outil enregistre qui a saisi quoi et quand.

### Vérification automatique

Pour chaque mois t, dès que le mois t+3 est importé, l'outil calcule `par90(t+3) − par90(t)` et classe le mois. Le décalage est l'horizon de vérification (3 mois), le même que pour la hausse sur trois mois.

| Niveau du mois t | Hausse réelle ≥ 0,5 point | Hausse réelle < 0,5 point |
| --- | --- | --- |
| Vigilance | Hausse confirmée | Signal non suivi d'une hausse |
| Alerte | La hausse s'est prolongée | Pas de nouvelle hausse |
| Normal | Hausse non signalée | Rien à signaler |

Un mois Non évalué garde sa hausse réelle, sans résultat de vérification, affiché « Sans objet ». Il ne compte dans aucun total de résultats.

Le mot « fausse alerte » n'apparaît nulle part : une Alerte constate une hausse déjà faite, et une revue suivie d'une action efficace peut expliquer l'absence de hausse.

### Affichage dans le bloc 6

- Décisions du mois affiché, avec un bouton d'ajout réservé à la direction.
- Signal émis trois mois plus tôt : niveau d'alors, PAR90 d'alors et d'aujourd'hui, hausse réelle, résultat, décisions prises à l'époque.
- Un lien ouvre le tableau récapitulatif de tous les mois : niveau, hausse réelle, résultat, et les totaux par résultat.

## 10. Modèle de données

Six tables suffisent. Les valeurs calculées sont stockées à côté des valeurs importées, pour que chaque mois garde le niveau qu'il avait le jour de sa clôture.

| Table | Une ligne par | Colonnes principales |
| --- | --- | --- |
| `imports` | fichier chargé | utilisateur, date, chemin du fichier archivé, avertissements, état (actif ou annulé) |
| `clotures` | mois de clôture | voir ci-dessous |
| `generations` | mois d'octroi × âge | `mois_octroi`, `age_mois` (0 à 12), `par30`, `par90`, `import_id` |
| `decisions` | décision | `cloture_id`, action, responsable, échéance, résultat, état, auteur, dates |
| `parametres` | jeu de paramètres | les cinq valeurs de la section 5, nom de l'institution, date d'effet |
| `users` | utilisateur | nom, e-mail, mot de passe, rôle (analyste ou direction) |

### Table `clotures`

| Groupe | Colonnes | Type |
| --- | --- | --- |
| Identité | `mois` (unique), `import_id`, `parametres_id`, `mode_calcul` (rétrospectif ou à la clôture) | date, clés, texte |
| Importé | `par30`, `par60`, `par90`, `par180`, `par365`, `impaye`, `rr_61_90` | `DECIMAL(10,6)`, vide autorisé sauf `par30`, `par60`, `par90` |
| Importé | `encours`, `par90_mt`, `radiations` | `BIGINT`, vide autorisé pour `radiations` |
| Calculé | `tranche_61_90`, `bond`, `hausse_3m`, `part_180`, `part_365`, `rr_connu` | `DECIMAL(10,6)`, vide autorisé |
| Calculé | `seuil_bond` | `DECIMAL(12,8)`, arrondi à 8 décimales, vide autorisé |
| Calculé | `nb_bonds`, `niveau`, `statut`, `motif` | entier, texte |
| Vérification | `hausse_reelle_3m`, `resultat_verif` | `DECIMAL(10,6)`, texte, vides tant que t+3 n'est pas importé |

### Règles

- `niveau`, `statut` et `resultat_verif` sont des chaînes contrôlées par le code, pas des types `ENUM` de la base : ajouter une valeur ne demande pas de migration lourde.
- Une ligne de `clotures` n'est jamais modifiée après création, sauf ses deux colonnes de vérification.
- La liste des bonds utilisés pour un seuil n'est pas stockée : elle se reconstitue à partir des lignes antérieures, qui sont figées.
- Le journal des actions (import, annulation, décision) est tenu par un paquet d'audit standard.

## 11. Choix techniques

Laravel, Vue 3 et MariaDB 10.11 (pilote mysql) : le calcul est simple, et le reste du travail (comptes, droits, import Excel, registre, traçabilité) est ce que Laravel fournit déjà.

| Brique | Choix | Raison |
| --- | --- | --- |
| Serveur | Laravel | Maîtrisé par le développeur ; hébergeable partout, y compris sur un serveur interne |
| Interface | Vue 3 avec Inertia | Une seule application à déployer, pas d'API séparée à maintenir |
| Base de données | MariaDB 10.11 (pilote mysql) | Le type `DECIMAL` y est exact ; rien dans ce projet n'exige PostgreSQL |
| Graphiques | Chart.js | Une courbe et une ligne de repère suffisent |
| Lecture Excel | PhpSpreadsheet (via Laravel Excel) | Lit le fichier modèle sans conversion |
| Droits | Policies Laravel, deux rôles | L'analyste importe et annule ; la direction décide ; les deux consultent |
| Journal | Paquet d'audit (spatie/laravel-activitylog) | Qui a fait quoi, sans code maison |

### Trois règles d'architecture

1. **Moteur de calcul isolé.** Une classe PHP sans base de données ni framework : elle reçoit la liste des mois et les paramètres, elle rend les indicateurs, le seuil, le niveau et le statut. C'est elle que les tests de la section 12 vérifient.
2. **Aucun seuil dans le code.** Les cinq paramètres viennent de la table `parametres`.
3. **Démonstration hors ligne.** Pour la soutenance, l'application tourne sur un ordinateur portable sans connexion ; une commande charge les 33 mois en une fois.

Les requêtes passent par Eloquent, sans SQL propre à MySQL : un passage à PostgreSQL resterait possible plus tard.

## 12. Tests de recette

L'outil est accepté s'il redonne, à partir de `donnees_chap3.xlsx`, les valeurs ci-dessous. Elles ont été recalculées sur le fichier et correspondent au tableau I.1 et à l'annexe H du mémoire.

### Les 12 dates du mémoire

| Mois | PAR90 (%) | Hausse 3 mois (pt) | Bond (pt) | Seuil (pt) | Niveau | Hausse réelle à t+3 (pt) |
| --- | --- | --- | --- | --- | --- | --- |
| mai 2025 | 5,46 | +0,79 | −0,564 | 0,131 | Alerte | +0,08 |
| juin 2025 | 5,08 | +0,54 | +0,314 | 0,123 | Alerte | +0,66 |
| juil. 2025 | 5,10 | −0,12 | −0,037 | 0,133 | Normal | +1,13 |
| août 2025 | 5,54 | +0,08 | +0,202 | 0,132 | Vigilance | +0,74 |
| sept. 2025 | 5,74 | +0,66 | +0,075 | 0,135 | Alerte | −0,45 |
| oct. 2025 | 6,24 | +1,13 | −0,185 | 0,134 | Alerte | −0,77 |
| nov. 2025 | 6,28 | +0,74 | +0,231 | 0,132 | Alerte | −0,91 |
| déc. 2025 | 5,29 | −0,45 | −0,320 | 0,141 | Normal | +0,52 |
| janv. 2026 | 5,46 | −0,77 | −0,001 | 0,134 | Normal | +1,03 |
| févr. 2026 | 5,37 | −0,91 | +0,463 | 0,133 | Vigilance | +1,28 |
| mars 2026 | 5,81 | +0,52 | +0,343 | 0,146 | Alerte | +0,43 |
| avr. 2026 | 6,50 | +1,03 | −0,536 | 0,183 | Alerte | −0,25 |

### Autres points de contrôle

| Test | Résultat attendu |
| --- | --- |
| Totaux sur les 33 mois | 10 Alerte, 3 Vigilance, 17 Normal, 3 Non évalué |
| Mois hors limite | 28 sur 33 |
| Novembre 2023 à janvier 2024 | Non évalué (moins de 4 mois d'historique) |
| Premier seuil disponible | Décembre 2024 : 0,134 point, 12 bonds |
| Mars 2025 | Vigilance (bond +0,996 pour un seuil de 0,116) |
| Mars 2026, hausse sur trois mois | +0,52 : Alerte. C'est la valeur la plus proche du seuil dans les données ; aucun mois ne change de niveau par arrondi, mais la marge est de 0,02 point. |
| Juillet 2026 | Normal, hors limite ; seuil 0,135 ; 31 bonds ; taux de passage connu 73,44 % |
| Radiations | 10 mois affichés « n.r. », aucun à zéro |
| Mai, juin, juillet 2026 | Vérification « En attente » |
| Fichier sans la colonne `par60` | Import refusé, aucune ligne créée |
| Fichier privé du mois de mars 2025 | Avril 2025 sans bond ; juin 2025 Non évalué |
| Réimport du même fichier | Aucune ligne ajoutée ni modifiée |

Les douze premières lignes et les totaux sont codés comme tests automatiques du moteur de calcul.

## 13. Étapes de développement

Le tableau de bord est visible dès l'étape 3, avec les vraies données. Les étapes 4 à 6 le complètent sans le remettre en cause.

1. **Moteur de calcul et tests.** La classe isolée et les tests automatiques de la section 12. Fini quand les 12 dates et les totaux passent.
2. **Base et import.** Tables, lecture du fichier modèle, contrôles, commande de chargement des 33 mois. Fini quand la base contient 33 clôtures avec leurs niveaux.
3. **Page du tableau de bord.** Blocs 1 à 5 et sélecteur de mois. Fini quand l'écran de juillet 2026 affiche les valeurs de l'annexe H.
4. **Registre et vérification.** Saisie des décisions, classement à trois mois, tableau récapitulatif.
5. **Comptes et traçabilité.** Connexion, deux rôles, journal, annulation d'import.
6. **Préparation de la soutenance.** Installation sur l'ordinateur de démonstration, répétition du scénario, captures d'écran pour remplacer la maquette de la figure 5 si l'auteure le souhaite.

Si le temps manque avant la soutenance, les étapes 1 à 3 suffisent pour montrer un outil qui fonctionne ; les étapes 4 et 5 peuvent être présentées comme la suite prévue.
