<?php

namespace App\Import;

use RuntimeException;

/** L'import ne peut pas être annulé : une décision est inscrite au registre pour l'un de ses mois. */
class AnnulationRefusee extends RuntimeException {}
