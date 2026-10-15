<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Repository\GroupRepository;
use App\Repository\TeacherRepository;

/**
 * Importación del CSV «Materia y grupos» de Séneca para asignar docentes a los grupos del curso activo.
 *
 * Tiene tres pasos para poder mostrar una vista previa: {@see parse()} lee el fichero, {@see plan()}
 * calcula qué haría con cada fila y {@see apply()} lo ejecuta solo para las filas marcadas. El docente se
 * busca por nombre y apellidos exactos y el grupo por nombre exacto entre los del curso activo.
 */
final class GroupAssignmentImporter
{
    public const COLUMN_GROUP   = 'Unidad';
    public const COLUMN_TEACHER = 'Profesor/a';

    public function __construct(
        private readonly GroupRepository $groups,
        private readonly TeacherRepository $teachers,
    ) {}

    /**
     * Lee el CSV (UTF-8 o Windows-1252). Las filas sin unidad o sin «Apellidos, Nombre» se omiten y las
     * repetidas se unen en una.
     *
     * @return array{rows: list<array{group: string, teacher: string, firstName: string, lastName: string}>, skipped: int}
     * @throws \InvalidArgumentException con el mensaje «empty» (sin cabeceras) o «missing:<columna>»
     */
    public function parse(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF");
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream, escape: '');
        if ($headers === false || $headers[0] === null) {
            fclose($stream);
            throw new \InvalidArgumentException('empty');
        }

        /** @var array<string, int> $map */
        $map = array_flip(array_map('trim', $headers));
        foreach ([self::COLUMN_GROUP, self::COLUMN_TEACHER] as $required) {
            if (!isset($map[$required])) {
                fclose($stream);
                throw new \InvalidArgumentException('missing:' . $required);
            }
        }

        $rows    = [];
        $skipped = 0;
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $group     = trim((string) ($row[$map[self::COLUMN_GROUP]] ?? ''));
            $teacher   = trim((string) ($row[$map[self::COLUMN_TEACHER]] ?? ''));
            $nameParts = explode(', ', $teacher, 2);
            $lastName  = $nameParts[0];
            $firstName = $nameParts[1] ?? '';

            if ($group === '' || $firstName === '' || $lastName === '') {
                ++$skipped;
                continue;
            }

            $rows[self::key($group, $teacher)] ??= ['group' => $group, 'teacher' => $teacher, 'firstName' => $firstName, 'lastName' => $lastName];
        }
        fclose($stream);

        return ['rows' => array_values($rows), 'skipped' => $skipped];
    }

    /**
     * @param list<array{group: string, teacher: string, firstName: string, lastName: string}> $rows
     * @return list<GroupAssignmentRow>
     */
    public function plan(EducationalCentre $centre, array $rows): array
    {
        /** @var array<string, Group> $groupsByName */
        $groupsByName = [];
        foreach ($this->groups->findByActiveYearOfCentreOrderedByName($centre) as $group) {
            $groupsByName[mb_strtolower($group->getName())] = $group;
        }

        $plan = [];
        foreach ($rows as $row) {
            $key   = self::key($row['group'], $row['teacher']);
            $group = $groupsByName[mb_strtolower($row['group'])] ?? null;
            if ($group === null) {
                $plan[] = new GroupAssignmentRow($key, $row['group'], $row['teacher'], GroupAssignmentRow::UNKNOWN_GROUP, null, null);
                continue;
            }

            $teacher = $this->teachers->findByFullName($row['firstName'], $row['lastName']);
            if ($teacher === null) {
                $plan[] = new GroupAssignmentRow($key, $row['group'], $row['teacher'], GroupAssignmentRow::UNKNOWN_TEACHER, $group, null);
                continue;
            }

            $status = $group->getTeachers()->contains($teacher) ? GroupAssignmentRow::EXISTING : GroupAssignmentRow::NEW;
            $plan[] = new GroupAssignmentRow($key, $row['group'], $row['teacher'], $status, $group, $teacher);
        }

        return $plan;
    }

    /**
     * Añade los docentes a sus grupos, solo en las filas nuevas cuya clave está en la selección.
     *
     * @param list<GroupAssignmentRow> $plan
     * @param list<string>             $selectedKeys
     * @return int asignaciones creadas
     */
    public function apply(array $plan, array $selectedKeys): int
    {
        $selected = array_flip($selectedKeys);
        $linked   = 0;

        foreach ($plan as $row) {
            if (!$row->isActionable() || !isset($selected[$row->key]) || $row->group === null || $row->teacher === null) {
                continue;
            }
            if (!$row->group->getTeachers()->contains($row->teacher)) {
                $row->group->addTeacher($row->teacher);
                ++$linked;
            }
        }

        return $linked;
    }

    /** Clave estable de una fila: grupo y docente sin distinguir mayúsculas. */
    public static function key(string $group, string $teacher): string
    {
        return md5(mb_strtolower($group) . '|' . mb_strtolower($teacher));
    }
}
