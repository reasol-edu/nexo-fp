<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Worker;
use App\Entity\Workcenter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->wipeDatabase($manager);
        $manager->clear();

        // Persist all teachers first and flush so they get IDs in DB
        $admin = new Teacher(new PersonName('Admin', 'User'));
        $admin->setUsername('admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setAdmin(true);
        $manager->persist($admin);

        $aTeachers = $this->makeAdaLovelaceTeachers($manager);
        $mTeachers = $this->makeMonterrubioTeachers($manager);
        $manager->flush();
        // No clear — keep teachers managed so we can use them directly below

        // Build everything else without intermediate flush/clear
        [$aCentre, $aYear, , $aProgrammes, $aPyears] = $this->buildAdaLovelace($manager, $aTeachers);
        [$mCentre, $mYear, , $mProgrammes, $mPyears] = $this->buildMonterrubio($manager, $mTeachers);

        $mGroups = $this->buildGroups($manager, $aPyears, $aTeachers, 'M', 12);
        $sGroups = $this->buildGroups($manager, $mPyears, $mTeachers, 'S', 12);

        [, $mWorkcenters] = $this->buildCompanies($manager, $aCentre, $aTeachers);
        [, $sWorkcenters] = $this->buildCompanies($manager, $mCentre, $mTeachers, 's');

        $this->buildStays($manager, $aYear, $aProgrammes, $aPyears, $mGroups, $mWorkcenters, $aTeachers);
        $this->buildStays($manager, $mYear, $mProgrammes, $mPyears, $sGroups, $sWorkcenters, $mTeachers);

        $manager->flush();
    }

    // ── Limpieza de base de datos ─────────────────────────────────────────────

    private function wipeDatabase(ObjectManager $manager): void
    {
        $conn    = $manager->getConnection();
        $isMysql = str_contains(get_class($conn->getDatabasePlatform()), 'MySQL')
                || str_contains(get_class($conn->getDatabasePlatform()), 'MariaDB');

        $q = $isMysql
            ? fn(string $t) => '`' . $t . '`'
            : fn(string $t) => '"' . $t . '"';

        // Break the circular FK before anything else
        $conn->executeStatement('UPDATE educational_centre SET active_academic_year_id = NULL');

        // Delete in reverse dependency order (join tables first, then children, then parents)
        $stmts = [
            'DELETE FROM ' . $q('training_position_programme_year'),
            'DELETE FROM ' . $q('stay_students'),
            'DELETE FROM ' . $q('training_position'),
            'DELETE FROM ' . $q('stay'),
            'DELETE FROM ' . $q('comment'),
            'DELETE FROM ' . $q('company_audit'),
            'DELETE FROM ' . $q('company_liaisons'),
            'DELETE FROM ' . $q('company_workers'),
            'DELETE FROM ' . $q('worker'),
            'DELETE FROM ' . $q('workcenter'),
            'DELETE FROM ' . $q('company'),
            'DELETE FROM ' . $q('student_groups'),
            'DELETE FROM ' . $q('student'),
            'DELETE FROM ' . $q('group_tutor'),
            'DELETE FROM ' . $q('group_teacher'),
            'DELETE FROM ' . $q('group'),
            'DELETE FROM ' . $q('programme_coordinator'),
            'DELETE FROM ' . $q('programme_year'),
            'DELETE FROM ' . $q('programme'),
            'DELETE FROM ' . $q('professional_family'),
            'DELETE FROM ' . $q('teacher_academic_year'),
            'DELETE FROM ' . $q('educational_centre_admins'),
            'DELETE FROM ' . $q('academic_year'),
            'DELETE FROM ' . $q('educational_centre'),
            'DELETE FROM ' . $q('teacher'),
        ];

        foreach ($stmts as $sql) {
            $conn->executeStatement($sql);
        }
    }

    // ── Helpers de creación de docentes ──────────────────────────────────────

    /** @return array<string, Teacher> */
    private function makeAdaLovelaceTeachers(ObjectManager $manager): array
    {
        $data = [
            ['rafael.exposito',   'Rafael',         'Expósito Moreno'],
            ['carmen.diaz',       'Carmen',         'Díaz Jiménez'],
            ['francisco.molina',  'Francisco Javier', 'Molina Ruiz'],
            ['maria.garcia',      'María Dolores',  'García Fernández'],
            ['antonio.navarro',   'Antonio',        'Navarro Castillo'],
            ['laura.sanchez',     'Laura',          'Sánchez Torres'],
            ['diego.romero',      'Diego',          'Romero Vega'],
            ['isabel.lozano',     'Isabel',         'Lozano Herrera'],
            ['manuel.perez',      'Manuel',         'Pérez Blanco'],
            ['pilar.martinez',    'Pilar',          'Martínez Rueda'],
            ['roberto.guerrero',  'Roberto',        'Guerrero Campos'],
            ['cristina.vargas',   'Cristina',       'Vargas Morales'],
            ['beatriz.alonso',    'Beatriz',        'Alonso Serrano'],
            ['rodrigo.fuentes',   'Rodrigo',        'Fuentes Parra'],
            ['elena.caballero',   'Elena',          'Caballero Ruiz'],
            ['julio.medina',      'Julio',          'Medina Torres'],
            ['sofia.delgado',     'Sofía',          'Delgado Iglesias'],
            ['marcos.herrero',    'Marcos',         'Herrero Vidal'],
            ['alberto.cabrera',   'Alberto',        'Cabrera García'],
            ['nuria.lopez',       'Nuria',          'López Morales'],
            ['javier.ortega',     'Javier',         'Ortega Bravo'],
            ['anabelen.castro',   'Ana Belén',      'Castro Fuentes'],
            ['tomas.vazquez',     'Tomás',          'Vázquez Acosta'],
            ['rosamaria.serrano', 'Rosa María',     'Serrano Díaz'],
            ['fernando.ibanez',   'Fernando',       'Ibáñez Cano'],
            ['marta.ramos',       'Marta',          'Ramos Palacios'],
            ['sergio.gallego',    'Sergio',         'Gallego Nieto'],
            ['veronica.mora',     'Verónica',       'Mora Espinosa'],
            ['pablo.aguilar',     'Pablo',          'Aguilar Blanco'],
            ['concepcion.munoz',  'Concepción',     'Muñoz Aranda'],
            ['alvaro.suarez',     'Álvaro',         'Suárez Paredes'],
            ['patricia.rubio',    'Patricia',       'Rubio Fernández'],
            ['luis.carrasco',     'Luis',           'Carrasco Reyes'],
            ['sandra.dominguez',  'Sandra',         'Domínguez Orozco'],
            ['david.pozo',        'David',          'Pozo Santana'],
            ['inmaculada.pena',   'Inmaculada',     'Peña García'],
            ['oscar.cortes',      'Óscar',          'Cortés Nieto'],
            ['yolanda.jimenez',   'Yolanda',        'Jiménez Fuentes'],
            ['miguel.flores',     'Miguel Ángel',   'Flores Pérez'],
            ['lucia.campos',      'Lucía',          'Campos Herrero'],
            ['enrique.benitez',   'Enrique',        'Benítez Castro'],
            ['marina.herrera',    'Marina',         'Herrera López'],
            ['joseluis.pinto',    'José Luis',      'Pinto García'],
            ['amparo.gomez',      'Amparo',         'Gómez Sánchez'],
            ['carlos.cano',       'Carlos',         'Cano Moreno'],
            ['teresa.prieto',     'Teresa',         'Prieto Vega'],
            ['andres.moya',       'Andrés',         'Moya López'],
            ['gloria.romero',     'Gloria',         'Romero Herrera'],
            ['guillermo.ruiz',    'Guillermo',      'Ruiz Vidal'],
            ['victoria.navarro',  'Victoria',       'Navarro Gil'],
            ['alejandro.martin',  'Alejandro',      'Martín Díaz'],
            ['silvia.pacheco',    'Silvia',         'Pacheco Ruiz'],
            ['eduardo.medina',    'Eduardo',        'Medina Vargas'],
        ];

        return $this->persistTeachers($manager, $data, isGlobalAdmin: ['rafael.exposito']);
    }

    /** @return array<string, Teacher> */
    private function makeMonterrubioTeachers(ObjectManager $manager): array
    {
        $data = [
            ['mariajose.alvarez',       'María José',      'Álvarez García'],
            ['pedro.fernandez',         'Pedro Antonio',   'Fernández Rubio'],
            ['rosario.soto',            'Rosario',         'Soto Merino'],
            ['ignacio.crespo',          'Ignacio',         'Crespo Leal'],
            ['piedad.torres',           'Piedad',          'Torres Velázquez'],
            ['dolores.reyes',           'Dolores',         'Reyes Álvarez'],
            ['vicente.roldan',          'Vicente',         'Roldán Camacho'],
            ['carmenrosa.marin',        'Carmen Rosa',     'Marín Espejo'],
            ['antonia.guzman',          'Antonia',         'Guzmán Osuna'],
            ['josefa.naranjo',          'Josefa',          'Naranjo Hidalgo'],
            ['remedios.calvo',          'Remedios',        'Calvo Durán'],
            ['bartolome.morales',       'Bartolomé',       'Morales Cabello'],
            ['francisca.giron',         'Francisca',       'Girón Padilla'],
            ['sebastian.lara',          'Sebastián',       'Lara Nieto'],
            ['encarnacion.baena',       'Encarnación',     'Baena Vilches'],
            ['manuela.criado',          'Manuela',         'Criado Arroyo'],
            ['demetrio.gallardo',       'Demetrio',        'Gallardo Cruz'],
            ['amelia.fuentes',          'Amelia',          'Fuentes Olea'],
            ['isidoro.bueno',           'Isidoro',         'Bueno Salas'],
            ['remedios.ortiz',          'Remedios',        'Ortiz Pedrera'],
            ['alfonso.serrano',         'Alfonso',         'Serrano Rico'],
            ['montserrat.cobo',         'Montserrat',      'Cobo Rivas'],
            ['gonzalo.torres',          'Gonzalo',         'Torres Jurado'],
            ['esperanza.ruiz',          'Esperanza',       'Ruiz Calero'],
            ['horacio.lopez',           'Horacio',         'López Bravo'],
            ['natividad.moreno',        'Natividad',       'Moreno Navarro'],
            ['dionisio.garcia',         'Dionisio',        'García Blanco'],
            ['rosalia.campos',          'Rosalía',         'Campos Vega'],
            ['teodoro.herrero',         'Teodoro',         'Herrero Reina'],
            ['milagros.jimenez',        'Milagros',        'Jiménez Villar'],
            ['fermin.castillo',         'Fermín',          'Castillo Pérez'],
            ['olimpia.santana',         'Olimpia',         'Santana Durán'],
            ['aurelio.gomez',           'Aurelio',         'Gómez Márquez'],
            ['fatima.palacios',         'Fátima',          'Palacios Estrada'],
            ['celestino.ramos',         'Celestino',       'Ramos Garrido'],
            ['azucena.suarez',          'Azucena',         'Suárez Montoro'],
            ['esteban.maldonado',       'Esteban',         'Maldonado Cid'],
            ['presentacion.delgado',    'Presentación',    'Delgado Cuenca'],
            ['wenceslao.cruz',          'Wenceslao',       'Cruz Carrillo'],
            ['purificacion.aguilar',    'Purificación',    'Aguilar Peña'],
            ['leopoldo.bravo',          'Leopoldo',        'Bravo Solano'],
            ['candelaria.munoz',        'Candelaria',      'Muñoz Serrano'],
            ['ezequiel.toro',           'Ezequiel',        'Toro Caballero'],
            ['adoracion.haro',          'Adoración',       'Haro Gutiérrez'],
            ['serafin.vidal',           'Serafín',         'Vidal Peña'],
            ['leonor.molina',           'Leonor',          'Molina Fuentes'],
            ['anselmo.perez',           'Anselmo',         'Pérez Lozano'],
            ['concepcion.barroso',      'Concepción',      'Barroso Gil'],
            ['baltasar.herrera',        'Baltasar',        'Herrera Mena'],
            ['amparo.romero',           'Amparo',          'Romero Durán'],
            ['inocencio.garcia',        'Inocencio',       'García Quesada'],
            ['visitacion.blanco',       'Visitación',      'Blanco Mora'],
        ];

        return $this->persistTeachers($manager, $data, isGlobalAdmin: ['mariajose.alvarez']);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $data
     * @param string[] $isGlobalAdmin
     * @return array<string, Teacher>
     */
    private function persistTeachers(ObjectManager $manager, array $data, array $isGlobalAdmin = []): array
    {
        $teachers = [];
        foreach ($data as [$username, $first, $last]) {
            $t = new Teacher(new PersonName($first, $last));
            $t->setUsername($username);
            $t->setPassword($this->passwordHasher->hashPassword($t, $username));
            if (in_array($username, $isGlobalAdmin, true)) {
                $t->setAdmin(true);
            }
            $manager->persist($t);
            $teachers[$username] = $t;
        }
        return $teachers;
    }

    // ── Estructura académica ──────────────────────────────────────────────────

    /**
     * @param array<string, Teacher> $teachers
     * @return array{EducationalCentre, AcademicYear, ProfessionalFamily[], Programme[], ProgrammeYear[]}
     */
    private function buildAdaLovelace(ObjectManager $manager, array $teachers): array
    {
        $centre = (new EducationalCentre())
            ->setCode('23006123')
            ->setName('IES Ada Lovelace')
            ->setCity('Linares');

        $year = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $centre->setActiveAcademicYear($year);
        $manager->persist($centre);
        $manager->persist($year);

        $centre->addAdmin($teachers['rafael.exposito']);
        $centre->addAdmin($teachers['carmen.diaz']);
        foreach ($teachers as $t) {
            $year->addTeacher($t);
        }

        $ic  = (new ProfessionalFamily())->setName('Informática y Comunicaciones')->setAcademicYear($year)->setHead($teachers['francisco.molina']);
        $san = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year)->setHead($teachers['isabel.lozano']);
        $manager->persist($ic);
        $manager->persist($san);

        $programmes = [];
        $pyears     = [];

        $smr = $this->makeProgramme($manager, 'CFGM Sistemas Microinformáticos y Redes', $ic, $year, $teachers['maria.garcia']);
        [$py1smr, $py2smr] = $this->makeProgrammeYears($manager, $smr, 'SMR');
        $programmes[] = $smr; $pyears[] = $py1smr; $pyears[] = $py2smr;

        $asir = $this->makeProgramme($manager, 'CFGS Administración de Sistemas Informáticos en Red', $ic, $year, $teachers['antonio.navarro']);
        [$py1asir, $py2asir] = $this->makeProgrammeYears($manager, $asir, 'ASIR');
        $programmes[] = $asir; $pyears[] = $py1asir; $pyears[] = $py2asir;

        $dam = $this->makeProgramme($manager, 'CFGS Desarrollo de Aplicaciones Multiplataforma', $ic, $year, $teachers['laura.sanchez']);
        [$py1dam, $py2dam] = $this->makeProgrammeYears($manager, $dam, 'DAM');
        $programmes[] = $dam; $pyears[] = $py1dam; $pyears[] = $py2dam;

        $daw = $this->makeProgramme($manager, 'CFGS Desarrollo de Aplicaciones Web', $ic, $year, $teachers['diego.romero']);
        [$py1daw, $py2daw] = $this->makeProgrammeYears($manager, $daw, 'DAW');
        $programmes[] = $daw; $pyears[] = $py1daw; $pyears[] = $py2daw;

        $caue = $this->makeProgramme($manager, 'CFGM Cuidados Auxiliares de Enfermería', $san, $year, $teachers['manuel.perez']);
        [$py1caue, $py2caue] = $this->makeProgrammeYears($manager, $caue, 'CAUE');
        $programmes[] = $caue; $pyears[] = $py1caue; $pyears[] = $py2caue;

        $em = $this->makeProgramme($manager, 'CFGM Emergencias Sanitarias', $san, $year, $teachers['pilar.martinez']);
        [$py1em, $py2em] = $this->makeProgrammeYears($manager, $em, 'ES');
        $programmes[] = $em; $pyears[] = $py1em; $pyears[] = $py2em;

        $hb = $this->makeProgramme($manager, 'CFGS Higiene Bucodental', $san, $year, $teachers['roberto.guerrero']);
        [$py1hb, $py2hb] = $this->makeProgrammeYears($manager, $hb, 'HB');
        $programmes[] = $hb; $pyears[] = $py1hb; $pyears[] = $py2hb;

        $ap = $this->makeProgramme($manager, 'CFGS Audiología Protésica', $san, $year, $teachers['cristina.vargas']);
        [$py1ap, $py2ap] = $this->makeProgrammeYears($manager, $ap, 'AP');
        $programmes[] = $ap; $pyears[] = $py1ap; $pyears[] = $py2ap;

        return [$centre, $year, [$ic, $san], $programmes, $pyears];
    }

    /**
     * @param array<string, Teacher> $teachers
     * @return array{EducationalCentre, AcademicYear, ProfessionalFamily[], Programme[], ProgrammeYear[]}
     */
    private function buildMonterrubio(ObjectManager $manager, array $teachers): array
    {
        $centre = (new EducationalCentre())
            ->setCode('41017845')
            ->setName('IES Monterrubio')
            ->setCity('Utrera');

        $year = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $centre->setActiveAcademicYear($year);
        $manager->persist($centre);
        $manager->persist($year);

        $centre->addAdmin($teachers['mariajose.alvarez']);
        $centre->addAdmin($teachers['pedro.fernandez']);
        foreach ($teachers as $t) {
            $year->addTeacher($t);
        }

        $ic  = (new ProfessionalFamily())->setName('Informática y Comunicaciones')->setAcademicYear($year)->setHead($teachers['rosario.soto']);
        $ssc = (new ProfessionalFamily())->setName('Servicios Socioculturales y a la Comunidad')->setAcademicYear($year)->setHead($teachers['dolores.reyes']);
        $ip  = (new ProfessionalFamily())->setName('Imagen Personal')->setAcademicYear($year)->setHead($teachers['antonia.guzman']);
        $manager->persist($ic);
        $manager->persist($ssc);
        $manager->persist($ip);

        $programmes = [];
        $pyears     = [];

        $smr = $this->makeProgramme($manager, 'CFGM Sistemas Microinformáticos y Redes', $ic, $year, $teachers['ignacio.crespo']);
        [$py1smr, $py2smr] = $this->makeProgrammeYears($manager, $smr, 'SMR');
        $programmes[] = $smr; $pyears[] = $py1smr; $pyears[] = $py2smr;

        $daw = $this->makeProgramme($manager, 'CFGS Desarrollo de Aplicaciones Web', $ic, $year, $teachers['piedad.torres']);
        [$py1daw, $py2daw] = $this->makeProgrammeYears($manager, $daw, 'DAW');
        $programmes[] = $daw; $pyears[] = $py1daw; $pyears[] = $py2daw;

        $is = $this->makeProgramme($manager, 'CFGS Integración Social', $ssc, $year, $teachers['vicente.roldan']);
        [$py1is, $py2is] = $this->makeProgrammeYears($manager, $is, 'IS');
        $programmes[] = $is; $pyears[] = $py1is; $pyears[] = $py2is;

        $pig = $this->makeProgramme($manager, 'CFGS Promoción de Igualdad de Género', $ssc, $year, $teachers['carmenrosa.marin']);
        [$py1pig, $py2pig] = $this->makeProgrammeYears($manager, $pig, 'PIG');
        $programmes[] = $pig; $pyears[] = $py1pig; $pyears[] = $py2pig;

        $pcc = $this->makeProgramme($manager, 'CFGM Peluquería y Cuidados Capilares', $ip, $year, $teachers['josefa.naranjo']);
        [$py1pcc, $py2pcc] = $this->makeProgrammeYears($manager, $pcc, 'PCC');
        $programmes[] = $pcc; $pyears[] = $py1pcc; $pyears[] = $py2pcc;

        $eb = $this->makeProgramme($manager, 'CFGS Estética y Belleza', $ip, $year, $teachers['remedios.calvo']);
        [$py1eb, $py2eb] = $this->makeProgrammeYears($manager, $eb, 'EB');
        $programmes[] = $eb; $pyears[] = $py1eb; $pyears[] = $py2eb;

        return [$centre, $year, [$ic, $ssc, $ip], $programmes, $pyears];
    }

    private function makeProgramme(
        ObjectManager $manager,
        string $name,
        ProfessionalFamily $family,
        AcademicYear $year,
        Teacher $coordinator,
    ): Programme {
        $p = (new Programme())
            ->setName($name)
            ->setProfessionalFamily($family)
            ->setAcademicYear($year);
        $p->addCoordinator($coordinator);
        $manager->persist($p);
        return $p;
    }

    /** @return array{ProgrammeYear, ProgrammeYear} */
    private function makeProgrammeYears(ObjectManager $manager, Programme $programme, string $abbr): array
    {
        $py1 = (new ProgrammeYear())->setName('1.º ' . $abbr)->setProgramme($programme);
        $py2 = (new ProgrammeYear())->setName('2.º ' . $abbr)->setProgramme($programme);
        $manager->persist($py1);
        $manager->persist($py2);
        return [$py1, $py2];
    }

    // ── Grupos y alumnos ──────────────────────────────────────────────────────

    /**
     * @param ProgrammeYear[] $pyears Pairs [py1, py2, py1, py2, ...]
     * @param array<string, Teacher> $teachers
     * @return Group[]
     */
    private function buildGroups(
        ObjectManager $manager,
        array $pyears,
        array $teachers,
        string $prefix,
        int $studentsPerGroup,
    ): array {
        $teacherList = array_values($teachers);
        $groups      = [];
        $tutorIdx    = 18; // start after special-role teachers

        foreach ($pyears as $i => $py) {
            $abbr    = $py->getName();
            $abbr    = preg_replace('/[^A-Z0-9]/i', '', $abbr) ?? $abbr;
            $group   = (new Group())
                ->setName($abbr . '-A')
                ->setProgrammeYear($py);

            $tutor = $teacherList[$tutorIdx % count($teacherList)];
            $group->addTutor($tutor);
            $tutorIdx += 2;

            $co = $teacherList[($tutorIdx + 1) % count($teacherList)];
            $group->addTeacher($co);

            $manager->persist($group);

            for ($s = 1; $s <= $studentsPerGroup; $s++) {
                $student = new Student(new PersonName(
                    $this->firstName($prefix, $i, $s),
                    $this->lastName($prefix, $i, $s),
                ));
                $student->setStudentId(sprintf('%s%03d%02d', strtoupper($prefix), $i + 1, $s));
                $student->addGroup($group);
                $manager->persist($student);
            }

            $groups[] = $group;
        }

        return $groups;
    }

    private function firstName(string $prefix, int $groupIdx, int $studentIdx): string
    {
        $names = ['Laura', 'Carlos', 'María', 'David', 'Lucía', 'Alejandro', 'Ana', 'Jorge',
                  'Sofía', 'Miguel', 'Paula', 'Adrián', 'Sara', 'Diego', 'Marta', 'Pablo',
                  'Carla', 'Álvaro', 'Elena', 'Sergio', 'Irene', 'Rubén', 'Alba', 'Víctor'];
        return $names[($groupIdx * 12 + $studentIdx) % count($names)];
    }

    private function lastName(string $prefix, int $groupIdx, int $studentIdx): string
    {
        $surnames = ['García', 'Martínez', 'López', 'Sánchez', 'González', 'Pérez', 'Rodríguez',
                     'Fernández', 'Jiménez', 'Moreno', 'Muñoz', 'Álvarez', 'Romero', 'Díaz',
                     'Herrera', 'Torres', 'Ruiz', 'Navarro', 'Molina', 'Blanco'];
        return $surnames[($groupIdx * 7 + $studentIdx * 3) % count($surnames)];
    }

    // ── Empresas ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, Teacher> $teachers
     * @return array{Company[], Workcenter[]}
     */
    private function buildCompanies(ObjectManager $manager, EducationalCentre $centre, array $teachers, string $set = 'm'): array
    {
        $isMonterrubio2 = $set === 'm';

        $companyData = $isMonterrubio2 ? [
            ['Repsol Química S.A.',              'B12300001', 'Linares'],
            ['Indra Sistemas S.L.',              'B12300002', 'Linares'],
            ['Telco Jaén S.L.',                  'B12300003', 'Linares'],
            ['Informática Linares S.L.',          'B12300004', 'Linares'],
            ['DataSystems Jaén S.L.',            'B12300005', 'Linares'],
            ['NetConsulting Sur S.L.',           'B12300006', 'Linares'],
            ['Hospital Comarcal de Linares',     'B12300007', 'Linares'],
            ['Clínica Virgen del Carmen S.L.',   'B12300008', 'Linares'],
            ['Centro Médico Jaén Norte S.L.',    'B12300009', 'Linares'],
            ['Farmacia Morales Cano S.L.',       'B12300010', 'Linares'],
            ['Auxiliar Sanitaria Sur S.L.',      'B12300011', 'Linares'],
            ['Ortopedia Pérez Garrido S.L.',     'B12300012', 'Linares'],
        ] : [
            ['Accenture Spain S.L.',              'B41300001', 'Sevilla'],
            ['Comex Informática S.L.',            'B41300002', 'Utrera'],
            ['Red Eléctrica IT Services S.L.',   'B41300003', 'Sevilla'],
            ['Eviden Spain S.L.',                'B41300004', 'Sevilla'],
            ['Grupo Vitalia Sevilla S.L.',       'B41300005', 'Sevilla'],
            ['Centro de Día Los Olivos S.L.',    'B41300006', 'Utrera'],
            ['Fundación Sevilla Integra',         'B41300007', 'Sevilla'],
            ['Servicios Sociales Utrera S.L.',   'B41300008', 'Utrera'],
            ['Peluquería Marta García S.L.',     'B41300009', 'Utrera'],
            ['Centro Estético Belleza Sur S.L.', 'B41300010', 'Sevilla'],
            ['Instituto Belleza Hispalense S.L.','B41300011', 'Sevilla'],
            ['Spa y Bienestar Guadalquivir S.L.','B41300012', 'Utrera'],
        ];

        // Each entry: [from, to, [usernames]]
        $liaisonGroups = $isMonterrubio2
            ? [
                [0,  5,  ['beatriz.alonso', 'rodrigo.fuentes']],
                [6,  8,  ['elena.caballero', 'julio.medina']],
                [9,  11, ['sofia.delgado',   'marcos.herrero']],
            ]
            : [
                [0,  3,  ['bartolome.morales', 'francisca.giron']],
                [4,  7,  ['sebastian.lara',    'encarnacion.baena']],
                [8,  11, ['manuela.criado']],
            ];

        $workerSurnames = ['Vega', 'Cano', 'Bravo', 'Pardo', 'Rueda', 'Oliva', 'Mena', 'Cruz',
                           'Salas', 'Nieto', 'Yuste', 'Lagos'];

        $companies   = [];
        $workcenters = [];

        foreach ($companyData as $idx => [$name, $cif, $city]) {
            $company = (new Company())
                ->setName($name)
                ->setVatNumber($cif)
                ->setCity($city)
                ->setEducationalCentre($centre);

            // Assign liaisons
            foreach ($liaisonGroups as [$from, $to, $liaisonUsernames]) {
                if ($idx >= $from && $idx <= $to) {
                    foreach ($liaisonUsernames as $lu) {
                        if (isset($teachers[$lu])) {
                            $company->addLiaison($teachers[$lu]);
                        }
                    }
                }
            }

            $manager->persist($company);
            $companies[] = $company;

            $wc = (new Workcenter())->setName($name)->setCity($city)->setCompany($company);
            $manager->persist($wc);
            $workcenters[] = $wc;

            $worker = new Worker(new PersonName('Responsable', $workerSurnames[$idx % count($workerSurnames)]));
            $worker->setNationalIdNumber(sprintf('%08dZ', $idx + ($isMonterrubio2 ? 10000000 : 20000000)));
            $company->addWorker($worker);
            $manager->persist($worker);
        }

        return [$companies, $workcenters];
    }

    // ── Estancias y posiciones ────────────────────────────────────────────────

    /**
     * @param Programme[]    $programmes  One per enseñanza
     * @param ProgrammeYear[] $pyears     Pairs [py1, py2, ...]
     * @param Group[]        $groups      One per ProgrammeYear (same order)
     * @param Workcenter[]   $workcenters
     * @param array<string, Teacher> $teachers
     */
    private function buildStays(
        ObjectManager $manager,
        AcademicYear $year,
        array $programmes,
        array $pyears,
        array $groups,
        array $workcenters,
        array $teachers,
    ): void {
        $teacherList = array_values($teachers);
        $wcCount     = count($workcenters);

        foreach ($programmes as $progIdx => $programme) {
            $py1GroupIdx = $progIdx * 2;
            $py2GroupIdx = $progIdx * 2 + 1;
            $py1         = $pyears[$py1GroupIdx] ?? $pyears[0];
            $py2         = $pyears[$py2GroupIdx] ?? $pyears[0];
            $group1      = $groups[$py1GroupIdx] ?? $groups[0];
            $group2      = $groups[$py2GroupIdx] ?? $groups[0];
            $tutor       = $teacherList[($progIdx * 3) % count($teacherList)];
            $wc0         = $workcenters[$progIdx % $wcCount];
            $wc1         = $workcenters[($progIdx + 1) % $wcCount];
            $wc2         = $workcenters[($progIdx + 2) % $wcCount];
            $wc3         = $workcenters[($progIdx + 3) % $wcCount];

            // Abbreviation derived from ProgrammeYear name (e.g. "1.º SMR" → "SMR")
            $abbr = preg_replace('/^\d+\.º\s+/', '', $py1->getName()) ?? '';

            // ── Estancia pasada (1.er semestre 2025-2026) ────────────────────
            $pastStay = (new Stay())
                ->setName('FCT ' . $abbr . ' 2025 (1.er semestre)')
                ->setAcademicYear($year)
                ->setProgramme($programme)
                ->setStartDate(new \DateTimeImmutable('2025-09-15'))
                ->setEndDate(new \DateTimeImmutable('2026-01-31'));
            $manager->persist($pastStay);

            $students1 = $group1->getStudents()->toArray();

            // 5 positions DONE+signed (alumnos 0–4)
            foreach (range(0, 4) as $si) {
                if (isset($students1[$si])) {
                    $pastStay->addStudent($students1[$si]);
                    $manager->persist((new TrainingPosition())
                        ->setStay($pastStay)
                        ->setWorkcenter($workcenters[($progIdx + $si) % $wcCount])
                        ->setAcademicTutor($tutor)
                        ->addProgrammeYear($py1)
                        ->setStudent($students1[$si])
                        ->setState(TrainingPositionState::DONE)
                        ->setSigned(true));
                }
            }
            // 2 alumnos matriculados sin puesto
            foreach ([5, 6] as $si) {
                if (isset($students1[$si])) {
                    $pastStay->addStudent($students1[$si]);
                }
            }

            // ── Estancia actual (2.º semestre 2025-2026) ─────────────────────
            $currentStay = (new Stay())
                ->setName('FCT ' . $abbr . ' 2026 (2.º semestre)')
                ->setAcademicYear($year)
                ->setProgramme($programme)
                ->setStartDate(new \DateTimeImmutable('2026-03-01'))
                ->setEndDate(new \DateTimeImmutable('2026-06-20'));
            $manager->persist($currentStay);

            $students2 = $group2->getStudents()->toArray();

            // pos-1: DRAFT, sin alumno
            $manager->persist((new TrainingPosition())
                ->setStay($currentStay)->setWorkcenter($wc0)->setAcademicTutor($tutor)
                ->addProgrammeYear($py2)->setState(TrainingPositionState::DRAFT));

            // pos-2: DRAFT, sin alumno
            $manager->persist((new TrainingPosition())
                ->setStay($currentStay)->setWorkcenter($wc1)->setAcademicTutor($tutor)
                ->addProgrammeYear($py2)->setState(TrainingPositionState::DRAFT));

            // pos-3: DRAFT, alumno asignado pero sin confirmar
            if (isset($students2[0])) {
                $currentStay->addStudent($students2[0]);
                $manager->persist((new TrainingPosition())
                    ->setStay($currentStay)->setWorkcenter($wc0)->setAcademicTutor($tutor)
                    ->addProgrammeYear($py2)->setStudent($students2[0])
                    ->setState(TrainingPositionState::DRAFT));
            }

            // pos-4: PENDING, sin firmar
            if (isset($students2[1])) {
                $currentStay->addStudent($students2[1]);
                $manager->persist((new TrainingPosition())
                    ->setStay($currentStay)->setWorkcenter($wc0)->setAcademicTutor($tutor)
                    ->addProgrammeYear($py2)->setStudent($students2[1])
                    ->setState(TrainingPositionState::PENDING));
            }

            // pos-5: PENDING, firmado
            if (isset($students2[2])) {
                $currentStay->addStudent($students2[2]);
                $manager->persist((new TrainingPosition())
                    ->setStay($currentStay)->setWorkcenter($wc2)->setAcademicTutor($tutor)
                    ->addProgrammeYear($py2)->setStudent($students2[2])
                    ->setState(TrainingPositionState::PENDING)->setSigned(true));
            }

            // pos-6: DONE, firmado
            if (isset($students2[3])) {
                $currentStay->addStudent($students2[3]);
                $manager->persist((new TrainingPosition())
                    ->setStay($currentStay)->setWorkcenter($wc0)->setAcademicTutor($tutor)
                    ->addProgrammeYear($py2)->setStudent($students2[3])
                    ->setState(TrainingPositionState::DONE)->setSigned(true));
            }

            // pos-7: DONE, firmado
            if (isset($students2[4])) {
                $currentStay->addStudent($students2[4]);
                $manager->persist((new TrainingPosition())
                    ->setStay($currentStay)->setWorkcenter($wc3)->setAcademicTutor($tutor)
                    ->addProgrammeYear($py2)->setStudent($students2[4])
                    ->setState(TrainingPositionState::DONE)->setSigned(true));
            }

            // 2 alumnos matriculados sin puesto
            foreach ([5, 6] as $si) {
                if (isset($students2[$si])) {
                    $currentStay->addStudent($students2[$si]);
                }
            }

            // ── Estancia futura (1.er semestre 2026-2027) ─────────────────────
            $futureStay = (new Stay())
                ->setName('FCT ' . $abbr . ' 2026-2027')
                ->setAcademicYear($year)
                ->setProgramme($programme)
                ->setStartDate(new \DateTimeImmutable('2026-09-15'))
                ->setEndDate(new \DateTimeImmutable('2027-01-31'));
            $manager->persist($futureStay);

            // 2 puestos DRAFT, sin alumnos
            $manager->persist((new TrainingPosition())
                ->setStay($futureStay)->setWorkcenter($wc0)->setAcademicTutor($tutor)
                ->addProgrammeYear($py2)->setState(TrainingPositionState::DRAFT));

            $manager->persist((new TrainingPosition())
                ->setStay($futureStay)->setWorkcenter($wc1)->setAcademicTutor($tutor)
                ->addProgrammeYear($py2)->setState(TrainingPositionState::DRAFT));
        }
    }
}
