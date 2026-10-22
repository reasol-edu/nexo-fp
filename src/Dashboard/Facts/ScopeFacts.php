<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

/**
 * Responsabilidades de una persona en el curso: qué gestiona, qué imparte y de qué empresas es enlace.
 * Los bloques del inicio se componen de ellas, no de la etiqueta de un rol.
 */
final readonly class ScopeFacts
{
    /**
     * @param array<string, string> $manageProgrammes   enseñanzas de las que coordina algún nivel o cuya familia dirige (id → nombre)
     * @param list<string>          $headedProgrammeIds enseñanzas cuya familia dirige (subconjunto de las anteriores)
     * @param list<GroupRef>        $groups             grupos en los que es tutor/a o docente
     * @param list<CompanyRef>      $companies          empresas de las que es docente de enlace
     * @param list<string>          $manageLevelIds     niveles que coordina o cuya familia dirige: la gestión se decide por nivel
     */
    public function __construct(
        public string $userId,
        public bool $isAdmin,
        public array $manageProgrammes,
        public array $headedProgrammeIds,
        public array $groups,
        public array $companies,
        public array $manageLevelIds = [],
    ) {}

    /** @return list<string> */
    public function manageProgrammeIds(): array
    {
        return array_map('strval', array_keys($this->manageProgrammes));
    }

    public function manages(): bool
    {
        return $this->isAdmin || $this->manageLevelIds !== [];
    }

    /** ¿Gestiona al menos un nivel de la estancia? */
    public function managesStay(StayFacts $stay): bool
    {
        return $this->isAdmin || array_intersect($stay->levelIds, $this->manageLevelIds) !== [];
    }

    public function isLiaison(): bool
    {
        return $this->companies !== [];
    }

    public function teaches(): bool
    {
        return $this->groups !== [];
    }

    /** ¿Ve la cobertura por enseñanza? La administración y quien dirige alguna familia. */
    public function seesCoverage(): bool
    {
        return $this->isAdmin || $this->headedProgrammeIds !== [];
    }

    /** @return list<string> */
    public function groupIds(): array
    {
        return array_map(static fn (GroupRef $g): string => $g->id, $this->groups);
    }

    /** @return list<string> */
    public function companyIds(): array
    {
        return array_map(static fn (CompanyRef $c): string => $c->id, $this->companies);
    }
}
