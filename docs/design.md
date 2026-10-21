# Design de l'outil d'alerte PAR90

Référence visuelle pour toutes les étapes de refonte. Une page.

## Parti pris

Un registre de comité de crédit, pas un tableau de bord grand public. L'écran répond d'abord à une question, « faut-il ouvrir une revue ? », et le niveau d'alerte est la seule chose qui crie. Tout le reste est calme : des feuilles découpées selon les questions du mémoire, chacune titrée par sa question. La couleur est un signal, jamais une décoration.

## Couleurs

Les couleurs de niveau sont réservées aux niveaux. Le bleu de Prusse est le seul accent : onglet actif, mois affiché, liens, focus, bouton principal d'un formulaire. Une erreur de saisie s'écrit en encre, en gras, avec une icône, jamais en rouge. Une action irréversible (annuler un import) est un bouton neutre à contour, avec icône, libellé explicite et fenêtre de confirmation.

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

- Barre supérieure de 48 px, pas de barre latérale sur le tableau de bord : produit et institution à gauche, onglets au centre, mois et menu à droite (Administration, thème, déconnexion).
- Trois onglets : Synthèse, Compréhension, Registre. Onglet et mois dans l'adresse ; l'ancienne adresse Générations mène à Compréhension.
- Zone de contenu en grille de 12 colonnes, gouttière de 16 px, marges de 16 px, largeur plafonnée à 1 760 px. Rien ne défile à 1366 × 768, sauf la liste des décisions et le tableau du récapitulatif, chacun dans son cadre.
- Synthèse : bandeau sur toute la largeur, le niveau en grand sur son aplat à gauche (lu en premier), le motif, l'action et la mention rétrospective au centre, le statut 5 % dans un cadre pointillé à droite. En dessous, la courbe sur 8 colonnes, la trajectoire et le signal avancé empilés sur 4.
- Compréhension : les huit indicateurs sur 5 colonnes, les générations sur 7.
- Registre : le signal émis trois mois plus tôt en bandeau compact, puis les décisions du mois et le formulaire d'ajout juste dessous.
- Récapitulatif : même barre, totaux en une rangée séparée par des filets (« Sans objet » et « En attente » à part, en pointillés), puis le tableau des clôtures.
- Back-office : même barre, navigation latérale de six sections, contenu sur une feuille. Pages de connexion et d'erreur : le produit à gauche, une feuille à droite.
- Texte aligné à gauche, nombres alignés à droite dans les tableaux.
- Rayons : 10 px pour la feuille, 6 px pour les contrôles, aucun pour les filets internes. Pas d'ombre portée, sauf sous le menu ouvert.

## Mouvement

Fondu de 150 ms entre onglets et entre mois, rien d'autre. Désactivé si le système demande moins d'animations.

## À ne pas faire

Dégradés, verre dépoli, néons, ombres lourdes, cartes identiques empilées, couleur de niveau utilisée hors d'un niveau, capitales espacées au-dessus des titres.
