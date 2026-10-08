# Outil d'alerte précoce PAR90

Tableau de bord mensuel d'une page pour une institution de microfinance. Il importe un fichier Excel mensuel, calcule un niveau d'alerte avec des règles fixes, l'affiche, et garde la trace des décisions de la direction. Pas de prévision, pas d'IA, pas de décision automatique.

Objectif immédiat : une v1 qui fonctionne en démonstration, hors ligne, sur un ordinateur portable.

## Documents de référence

- `docs/cahier-des-charges.md` : la spécification complète. Elle fait foi. Ne lis que la section utile à la tâche en cours.
- `data/donnees_chap3.xlsx` : les 33 mois de données réelles (novembre 2023 à juillet 2026). Confidentiel : jamais dans git, jamais copié dans le code ou les tests sous forme de fichier.

## Technique

- Laravel, Vue 3 avec Inertia, MariaDB 10.11 (pilote mysql), Chart.js.
- Lecture Excel : Laravel Excel (PhpSpreadsheet). Journal : spatie/laravel-activitylog.
- Tests : Pest.
- Requêtes via Eloquent, sans SQL propre à MySQL.

## Règles d'architecture

1. **Moteur de calcul isolé.** Une classe PHP pure dans `app/Domain/Alerte/`, sans Eloquent ni façade Laravel. Entrée : la liste des mois et les paramètres. Sortie : indicateurs, seuil, niveau, statut.
2. **Aucun seuil dans le code.** Les paramètres viennent de la configuration : limite 5 %, hausse 0,5 point, centile 70, minimum 12 bonds, horizon 3 mois.
3. **Une donnée absente reste absente.** `null` n'est jamais remplacé par 0. Un calcul dont une entrée manque rend `null`.
4. **Comparer avant d'arrondir.** Taux en `DECIMAL(10,6)`, seuil du bond en `DECIMAL(12,8)`, montants en `BIGINT`. L'arrondi ne se fait qu'à l'affichage, sauf le seuil, arrondi à 8 décimales avant d'être comparé et stocké.
5. **Une clôture importée ne se modifie plus**, sauf ses deux colonnes de vérification à trois mois.

## Le calcul, en bref

Pour le mois t (taux en %, écarts en points) :

- `tranche = par60 − par90`
- `bond = tranche(t) − tranche(t−1)`
- `hausse_3m = par90(t) − par90(t−h)`, h = horizon (3 mois) ; le même horizon sert à la vérification `par90(t+h) − par90(t)`
- `seuil` = 70e centile des bonds strictement antérieurs à t, interpolation linéaire (type 7 de R : `rang = 0,70 × (n − 1)`), seulement si n ≥ 12

Niveau, dans cet ordre :

1. `hausse_3m` non calculable : `NON_EVALUE`
2. `hausse_3m ≥ 0,5` : `ALERTE`
3. seuil disponible et `bond ≥ seuil` : `VIGILANCE`
4. sinon : `NORMAL`

Statut, indépendant du niveau : `HORS_LIMITE` si `par90 > 5`, sinon `DANS_LIMITE`.

Le taux de passage affiché au mois t est la valeur `rr_61_90` de la ligne t−1 du fichier.

## Valeurs de contrôle

Sur les 33 mois : 10 Alerte, 3 Vigilance, 17 Normal, 3 Non évalué ; 28 mois hors limite. Juillet 2026 : Normal, hors limite, seuil 0,135, 31 bonds. Le détail des 12 dates de référence est en section 12 du cahier des charges.

## Façon de travailler

- Une étape à la fois, celle qui est demandée. Ne commence pas la suivante.
- Avant d'installer un paquet qui n'est pas listé ici, demande.
- Si le cahier des charges est ambigu ou contredit les données, arrête-toi et pose la question au lieu de choisir.
- Code, noms de variables et commentaires en français pour le métier (`cloture`, `bond`, `seuil`), en anglais pour le technique habituel de Laravel.
- Aucune mention de Claude ni d'attribution dans les messages de commit.

## Rapport de fin d'étape

Termine chaque étape par un rapport court :

1. Ce qui est fait, en trois lignes au plus.
2. Fichiers créés ou modifiés.
3. Commande de test lancée et son résultat exact (nombre de tests passés et échoués).
4. Écarts par rapport au cahier des charges, s'il y en a, avec la raison.
5. Questions ouvertes.
