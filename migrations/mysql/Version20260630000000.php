<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630000000 extends AbstractMigration
{
    /**
     * Tablas de unión ManyToMany: [tabla, [constraint, columna, tabla referenciada], ...].
     * Doctrine mapea estas FK con ON DELETE CASCADE y deja en manos de la base de
     * datos el borrado de las filas de unión al eliminar una entidad.
     */
    private const JOIN_TABLES = [
        'stay_students'                  => [['FK_ss_stay', 'stay_id', 'stay'], ['FK_ss_student', 'student_id', 'student']],
        'training_position_programme_year' => [['FK_tppy_tp', 'training_position_id', 'training_position'], ['FK_tppy_py', 'programme_year_id', 'programme_year']],
        'group_teacher'                  => [['FK_gt_group', 'group_id', '`group`'], ['FK_gt_teacher', 'teacher_id', 'teacher']],
        'group_tutor'                    => [['FK_gtu_group', 'group_id', '`group`'], ['FK_gtu_teacher', 'teacher_id', 'teacher']],
        'student_groups'                 => [['FK_sg_student', 'student_id', 'student'], ['FK_sg_group', 'group_id', '`group`']],
        'educational_centre_admins'      => [['FK_eca_centre', 'educational_centre_id', 'educational_centre'], ['FK_eca_teacher', 'teacher_id', 'teacher']],
        'teacher_academic_year'          => [['FK_tay_year', 'academic_year_id', 'academic_year'], ['FK_tay_teacher', 'teacher_id', 'teacher']],
        'programme_coordinator'          => [['FK_pc_programme', 'programme_id', 'programme'], ['FK_pc_teacher', 'teacher_id', 'teacher']],
        'company_liaisons'               => [['FK_cl_company', 'company_id', 'company'], ['FK_cl_teacher', 'teacher_id', 'teacher']],
        'company_workers'                => [['FK_cw_company', 'company_id', 'company'], ['FK_cw_worker', 'worker_id', 'worker']],
    ];

    public function getDescription(): string
    {
        return 'Añade ON DELETE CASCADE a las claves foráneas de las tablas de unión ManyToMany (corrige el error 500 al borrar estancias con puestos) (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->recreate('ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->recreate('');
    }

    private function recreate(string $action): void
    {
        foreach (self::JOIN_TABLES as $table => $constraints) {
            foreach ($constraints as [$name, $column, $referenced]) {
                $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $name));
                $this->addSql(trim(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(id) %s',
                    $table,
                    $name,
                    $column,
                    $referenced,
                    $action,
                )));
            }
        }
    }
}
