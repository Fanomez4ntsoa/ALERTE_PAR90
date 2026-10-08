# Design de l'outil d'alerte PAR90

Référence visuelle pour toutes les étapes de refonte. Une page.

## Parti pris

Un registre de comité de crédit, pas un tableau de bord grand public. L'écran répond d'abord à une question, « faut-il ouvrir une revue ? », et le niveau d'alerte est la seule chose qui crie. Tout le reste est calme : une feuille unique découpée par des filets selon les questions du mémoire, plutôt qu'une pile de cartes identiques. La couleur est un signal, jamais une décoration.

## Couleurs

Les couleurs de niveau sont réservées aux niveaux. Le bleu de Prusse est le seul accent : onglet actif, mois affiché, liens, focus.

| Rôle | Clair | Sombre |
| --- | --- | --- |
| Fond | `#EDF0F2` gris bleuté | `#0E141B` ardoise |
| Feuille | `#FFFFFF` | `#151C26` |
| Encre | `#17202C` | `#E4E9EF` |
| Encre secondaire | `#4D5867` | `#9AA5B4` |
| Filet | `#D6DCE2` | `#26303D` |
| Accent | `#1D4A8F` bleu de Prusse | `#8EB1F2` |
| Normal | `#16774A` | `#34A874` |
| Vigilance | `#E3A300`, texte `#2B1D00` | `#E9B12E`, texte `#241800` |
| Alerte | `#B1241B` | `#E3564C` |
| Non évalué | `#687180` | `#7D8796` |

Le sombre n'est pas une inversion : fond ardoise bleutée, feuilles légèrement éclairées, couleurs de niveau ajustées pour garder le même contraste perçu, et texte foncé sur les aplats de niveau. L'impression est toujours en clair.

## Typographie

IBM Plex Sans, embarquée dans `resources/fonts` (licence OFL), graisses 400, 500, 600 et 700. Toutes les valeurs sont en chiffres tabulaires (`tabular-nums`) : les colonnes s'alignent sans police à chasse fixe. Plex Mono a été essayée et écartée : elle écarte trop les unités.

Échelle, en pixels à 1366 px de large : 12 (notes), 14 (texte), 16 (intitulés), 20 (valeurs), 28 (PAR90), 56 (niveau). Casse de phrase partout, jamais de capitales pour les étiquettes. Pas d'émoji, pas de tiret long.

## Grille et structure

- Barre supérieure de 48 px, pas de barre latérale : produit et institution à gauche, onglets au centre, mois et menu à droite.
- Quatre onglets : Synthèse, Compréhension, Générations, Registre. Onglet et mois dans l'adresse.
- Zone de contenu en grille de 12 colonnes, gouttière de 16 px, marges de 20 px. Rien ne défile à 1366 × 768, sauf la liste des décisions.
- Synthèse : colonne de verdict à gauche (4 colonnes) avec le niveau, le motif, l'action et le statut 5 % en contour, nettement distinct du niveau ; à droite (8 colonnes) le PAR90 et sa courbe, puis la trajectoire et le signal avancé côte à côte.
- Texte aligné à gauche, nombres alignés à droite dans les tableaux.
- Rayons : 10 px pour la feuille, 6 px pour les contrôles, aucun pour les filets internes. Pas d'ombre portée, sauf sous le menu ouvert.

## Mouvement

Fondu de 150 ms entre onglets et entre mois, rien d'autre. Désactivé si le système demande moins d'animations.

## À ne pas faire

Dégradés, verre dépoli, néons, ombres lourdes, cartes identiques empilées, couleur de niveau utilisée hors d'un niveau, capitales espacées au-dessus des titres.
