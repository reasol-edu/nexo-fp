<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

/** Un estudiante matriculado en una estancia, con los grupos que tiene en las enseñanzas de ésta. */
final readonly class StudentFacts
{
    /**
     * @param list<LevelFacts> $levels      vacío si no tiene grupo en ninguna enseñanza de la estancia
     * @param bool             $missingNuss consta en su ficha del curso que no dispone de NUSS
     *                                      (el alumnado que no realiza la FFEOE ni llega a cargarse)
     */
    public function __construct(
        public string $id,
        public array $levels,
        public bool $missingNuss = false,
    ) {}

    /** @return list<string> */
    public function programmeIds(): array
    {
        return array_values(array_unique(array_map(static fn (LevelFacts $l): string => $l->programmeId, $this->levels)));
    }

    /** @return list<string> */
    public function levelIds(): array
    {
        return array_values(array_unique(array_map(static fn (LevelFacts $l): string => $l->levelId, $this->levels)));
    }

    /** @return list<string> */
    public function groupIds(): array
    {
        return array_values(array_unique(array_map(static fn (LevelFacts $l): string => $l->groupId, $this->levels)));
    }
}
