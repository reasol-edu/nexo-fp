<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\StudentYearProfile;
use App\Entity\TrainingPosition;

/**
 * Cuánto encaja un puesto libre con la ficha del estudiante, para ofrecer primero los más razonables
 * al asignar: la empresa que el propio estudiante aporta, su localidad preferente y su localidad de
 * residencia. No hay datos de distancia, así que solo se compara por nombre (sin acentos ni mayúsculas).
 */
final class PositionFit
{
    public const COMPANY   = 'company';
    public const PREFERRED = 'preferred';
    public const RESIDENCE = 'residence';

    /** Orden de aparición: menor es antes. */
    private const RANK = [self::COMPANY => 0, self::PREFERRED => 1, self::RESIDENCE => 2];

    /** La etiqueta de encaje del puesto, o null si no encaja por ningún criterio. */
    public static function of(TrainingPosition $position, ?StudentYearProfile $profile): ?string
    {
        $workcenter = $position->getWorkcenter();
        if ($profile === null || $workcenter === null) {
            return null;
        }

        if ($profile->bringsCompany() && self::sameCompany($profile->getBroughtCompanyName(), $workcenter->getCompany()->getName())) {
            return self::COMPANY;
        }
        $city = self::normalise($workcenter->getCity());
        if ($city !== '') {
            if ($city === self::normalise($profile->getPreferredCity())) {
                return self::PREFERRED;
            }
            if ($city === self::normalise($profile->getResidenceCity())) {
                return self::RESIDENCE;
            }
        }

        return null;
    }

    /**
     * Ordena los puestos por encaje, conservando el orden de entrada dentro de cada nivel.
     *
     * @param list<TrainingPosition> $positions
     *
     * @return array{0: list<TrainingPosition>, 1: array<string, string>} los puestos ordenados y la etiqueta
     *                                                                     de cada uno que encaja (por id del puesto)
     */
    public static function rank(array $positions, ?StudentYearProfile $profile): array
    {
        $fits  = [];
        $keyed = [];
        foreach ($positions as $i => $position) {
            $fit = self::of($position, $profile);
            if ($fit !== null) {
                $fits[$position->getId()->toRfc4122()] = $fit;
            }
            $keyed[] = [$fit === null ? \count(self::RANK) : self::RANK[$fit], $i, $position];
        }
        usort($keyed, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return [array_map(static fn (array $k): TrainingPosition => $k[2], $keyed), $fits];
    }

    /** Texto libre del estudiante contra el nombre de la empresa: coincide si uno contiene al otro. */
    private static function sameCompany(?string $brought, string $company): bool
    {
        $brought = self::normalise($brought);
        $company = self::normalise($company);

        // La parte contenida debe tener cierta longitud: «Eta» no es la empresa «Beta».
        return (mb_strlen($brought) >= 4 && str_contains($company, $brought))
            || (mb_strlen($company) >= 4 && str_contains($brought, $company));
    }

    private static function normalise(?string $text): string
    {
        $text = strtr(mb_strtolower(trim((string) $text)), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u',
        ]);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
