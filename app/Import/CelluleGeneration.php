<?php

namespace App\Import;

/** Une cellule retenue des onglets de générations. */
final readonly class CelluleGeneration
{
    public function __construct(
        public string $moisOctroi,
        public int $ageMois,
        public ?string $par30,
        public ?string $par90,
    ) {}
}
