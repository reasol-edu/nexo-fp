<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TeacherAssignmentCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TeacherAssignmentCalculatorTest extends TestCase
{
    public function testSplitsProportionallyToTheHours(): void
    {
        $result = (new TeacherAssignmentCalculator())->distribute(20, ['a' => 60, 'b' => 30, 'c' => 10]);

        self::assertSame(['a' => 12, 'b' => 6, 'c' => 2], $result);
    }

    public function testTheLargestRemaindersGetTheLeftOverStudents(): void
    {
        // 10 estudiantes entre 3 docentes con las mismas horas: 3,33 cada uno → uno se queda con 4.
        $result = (new TeacherAssignmentCalculator())->distribute(10, ['a' => 5, 'b' => 5, 'c' => 5]);

        self::assertSame(10, array_sum($result));
        self::assertSame([3, 3, 4], $this->sorted($result));
        self::assertSame(['a' => 4, 'b' => 3, 'c' => 3], $result);   // empate: gana el primero
    }

    public function testATeacherWithoutHoursGetsNoStudents(): void
    {
        $result = (new TeacherAssignmentCalculator())->distribute(7, ['a' => 40, 'b' => 0]);

        self::assertSame(['a' => 7, 'b' => 0], $result);
    }

    public function testWithoutHoursNobodyGetsStudents(): void
    {
        self::assertSame(['a' => 0, 'b' => 0], (new TeacherAssignmentCalculator())->distribute(7, ['a' => 0, 'b' => 0]));
    }

    public function testWithoutStudentsNobodyGetsStudents(): void
    {
        self::assertSame(['a' => 0, 'b' => 0], (new TeacherAssignmentCalculator())->distribute(0, ['a' => 10, 'b' => 20]));
    }

    public function testNegativeHoursAreTreatedAsZero(): void
    {
        self::assertSame(['a' => 5, 'b' => 0], (new TeacherAssignmentCalculator())->distribute(5, ['a' => 10, 'b' => -4]));
    }

    public function testTheResultIsStable(): void
    {
        $calculator = new TeacherAssignmentCalculator();

        self::assertSame(
            $calculator->distribute(10, ['a' => 5, 'b' => 5, 'c' => 5]),
            $calculator->distribute(10, ['a' => 5, 'b' => 5, 'c' => 5]),
        );
    }

    /**
     * La suma de lo que debe tener cada docente coincide siempre con el número de estudiantes del grupo.
     *
     * @param array<string, int> $hours
     */
    #[DataProvider('hoursProvider')]
    public function testTheTotalAlwaysMatchesTheNumberOfStudents(int $students, array $hours): void
    {
        $result = (new TeacherAssignmentCalculator())->distribute($students, $hours);

        self::assertSame(array_keys($hours), array_keys($result));
        self::assertSame($students, array_sum($result), 'el reparto no suma el número de estudiantes');
        foreach ($result as $count) {
            self::assertGreaterThanOrEqual(0, $count);
        }
    }

    /** @return iterable<string, array{int, array<string, int>}> */
    public static function hoursProvider(): iterable
    {
        $sets = [
            [1, 1, 1], [3, 3, 3, 3, 3, 3, 3], [100, 1], [7, 11, 13, 17], [64, 96, 32], [5], [2, 3, 5, 7, 11, 13],
        ];
        foreach ($sets as $i => $set) {
            for ($students = 1; $students <= 31; $students++) {
                $hours = [];
                foreach ($set as $k => $h) {
                    $hours['t' . $k] = $h;
                }
                yield "caso $i con $students estudiantes" => [$students, $hours];
            }
        }
    }

    /**
     * @param array<string, int> $result
     * @return list<int>
     */
    private function sorted(array $result): array
    {
        $values = array_values($result);
        sort($values);

        return $values;
    }
}
