<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AcademicYear;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prepara el curso siguiente a partir de uno existente: crea el curso nuevo, copia la oferta
 * formativa (familias, enseñanzas, niveles y grupos, con las personas asignadas si se pide) y
 * añade al nuevo curso al profesorado del anterior. El alumnado no se copia: se importa de nuevo.
 */
final class AcademicYearRollover
{
    public function __construct(
        private readonly OfertaFormativaExporter $exporter,
        private readonly OfertaFormativaImporter $importer,
        private readonly TeacherRepository $teachers,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Nombre propuesto para el curso siguiente: «2025-2026» → «2026-2027» (o «2025/2026» → «2026/2027»).
     * Si el nombre no sigue ese patrón, devuelve cadena vacía para que lo escriba la persona.
     */
    public function suggestName(AcademicYear $source): string
    {
        if (preg_match('/^(\d{4})(\D+)(\d{4})$/', trim($source->getName()), $m) === 1) {
            return ((int) $m[1] + 1) . $m[2] . ((int) $m[3] + 1);
        }

        return '';
    }

    /** @return list<\App\Entity\Teacher> */
    private function teachersOf(AcademicYear $year): array
    {
        /** @var list<\App\Entity\Teacher> $teachers */
        $teachers = $this->teachers->createByAcademicYearFilteredQuery($year)->getResult();

        return $teachers;
    }

    /**
     * Qué se copiaría del curso de origen.
     *
     * @return array{families: int, programmes: int, levels: int, groups: int, teachers: int}
     */
    public function preview(AcademicYear $source): array
    {
        $counts = ['families' => 0, 'programmes' => 0, 'levels' => 0, 'groups' => 0, 'teachers' => \count($this->teachersOf($source))];

        foreach ((array) ($this->exporter->export($source)['families'] ?? []) as $family) {
            ++$counts['families'];
            foreach ((array) ($family['programmes'] ?? []) as $programme) {
                ++$counts['programmes'];
                foreach ((array) ($programme['levels'] ?? []) as $level) {
                    ++$counts['levels'];
                    $counts['groups'] += \count((array) ($level['groups'] ?? []));
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{year: AcademicYear, offer: array{families: int, programmes: int, levels: int, groups: int, missing_teachers: list<string>}|null, teachers: int}
     */
    public function rollOver(
        AcademicYear $source,
        string $name,
        bool $copyOffer,
        ImportOptions $options,
        bool $copyTeachers,
        bool $activate,
    ): array {
        $year = (new AcademicYear())
            ->setName($name)
            ->setEducationalCentre($source->getEducationalCentre());
        $this->em->persist($year);

        $teachers = 0;
        if ($copyTeachers) {
            foreach ($this->teachersOf($source) as $teacher) {
                $teacher->addAcademicYear($year);
                ++$teachers;
            }
        }

        // Los docentes se añaden antes de copiar la oferta para que las asignaciones por usuario los encuentren.
        $this->em->flush();

        $offer = $copyOffer ? $this->importer->import($this->exporter->export($source), $year, $options) : null;

        if ($activate) {
            $source->getEducationalCentre()->setActiveAcademicYear($year);
        }
        $this->em->flush();

        return ['year' => $year, 'offer' => $offer, 'teachers' => $teachers];
    }
}
