<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reparte los estudiantes de un grupo entre sus docentes en proporción a las horas dualizadas que cada
 * uno imparte en el grupo frente al total de horas dualizadas del grupo.
 *
 * El reparto es por el método del mayor resto: cada docente recibe la parte entera de su cuota y los
 * estudiantes que sobran se dan a quienes tienen mayor parte decimal. Así **la suma de lo que debe
 * tener cada docente es siempre exactamente el número de estudiantes del grupo en la estancia**.
 */
final class TeacherAssignmentCalculator
{
    /**
     * @param int                $students       estudiantes del grupo asociados a la estancia
     * @param array<string, int> $hoursByTeacher horas dualizadas por docente (clave = identificador del docente)
     *
     * @return array<string, int> estudiantes que debería tener cada docente (mismas claves que la entrada);
     *                            todos a 0 si no hay horas indicadas
     */
    public function distribute(int $students, array $hoursByTeacher): array
    {
        $hoursByTeacher = array_map(static fn (int $h): int => max(0, $h), $hoursByTeacher);
        $expected       = array_fill_keys(array_keys($hoursByTeacher), 0);
        $totalHours     = array_sum($hoursByTeacher);

        if ($students <= 0 || $totalHours <= 0) {
            return $expected;
        }

        // Cuota exacta = students × horas / total. Se trabaja con el numerador entero para no perder precisión.
        $remainders = [];
        $assigned   = 0;
        foreach ($hoursByTeacher as $teacherId => $hours) {
            $numerator            = $students * $hours;
            $expected[$teacherId] = intdiv($numerator, $totalHours);
            $remainders[$teacherId] = $numerator % $totalHours;
            $assigned            += $expected[$teacherId];
        }

        // Los estudiantes que faltan por repartir van a quienes tienen mayor resto; en empate, al que
        // imparte más horas y, si persiste, por orden de aparición (resultado estable).
        $order = array_keys($hoursByTeacher);
        usort($order, static function (string|int $a, string|int $b) use ($remainders, $hoursByTeacher, $order): int {
            return $remainders[$b] <=> $remainders[$a]
                ?: $hoursByTeacher[$b] <=> $hoursByTeacher[$a]
                ?: array_search($a, $order, true) <=> array_search($b, $order, true);
        });

        for ($i = 0, $pending = $students - $assigned; $pending > 0; $i++, $pending--) {
            $expected[$order[$i % \count($order)]]++;
        }

        return $expected;
    }
}
