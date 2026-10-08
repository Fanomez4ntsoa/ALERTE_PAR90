<?php

use App\Tableau\Format;
use Carbon\CarbonImmutable;

function sansInsecable(string $texte): string
{
    return str_replace("\u{00A0}", ' ', $texte);
}

it('met en forme taux, écarts, seuils et montants à la française', function () {
    expect(sansInsecable(Format::taux('6.254000')))->toBe('6,25 %')
        ->and(sansInsecable(Format::taux('78.480000', 1)))->toBe('78,5 %')
        ->and(sansInsecable(Format::ecart('-0.250000')))->toBe('−0,25 point')
        ->and(sansInsecable(Format::ecart('0.520000')))->toBe('+0,52 point')
        ->and(sansInsecable(Format::ecart('-0.004000')))->toBe('0,00 point')
        ->and(sansInsecable(Format::points('0.13517300', 3)))->toBe('0,135 point')
        ->and(sansInsecable(Format::milliards(12_794_000_000)))->toBe('12,79 Md Ar')
        ->and(sansInsecable(Format::taux('1234.5')))->toBe('1 234,50 %');
});

it('arrondit au plus proche en décimal exact, demi vers l\'extérieur', function () {
    expect(Format::nombre('0.125', 2))->toBe('0,13')
        ->and(Format::nombre('-0.125', 2))->toBe('−0,13')
        ->and(Format::nombre('2.675', 2))->toBe('2,68')
        ->and(Format::nombre('0.0049', 2))->toBe('0,00');
});

it('affiche n.r. pour toute valeur absente', function () {
    expect(Format::taux(null))->toBe('n.r.')
        ->and(Format::ecart(null))->toBe('n.r.')
        ->and(Format::points(null, 3))->toBe('n.r.')
        ->and(Format::milliards(null))->toBe('n.r.');
});

it('écrit les mois en français', function () {
    expect(Format::mois(CarbonImmutable::create(2026, 7, 1)))->toBe('juillet 2026')
        ->and(Format::moisCourt(CarbonImmutable::create(2026, 2, 1)))->toBe('févr. 26');
});
