<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cálculo de asignaciones por docente: stay.calculate_by_teacher y tabla stay_teacher_hours (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE stay ADD calculate_by_teacher TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_teacher_hours (
                id         BINARY(16) NOT NULL,
                stay_id    BINARY(16) NOT NULL,
                group_id   BINARY(16) NOT NULL,
                teacher_id BINARY(16) NOT NULL,
                hours      INT        NOT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uq_sth (stay_id, group_id, teacher_id),
                INDEX idx_sth_group (group_id),
                INDEX idx_sth_teacher (teacher_id),
                CONSTRAINT fk_sth_stay    FOREIGN KEY (stay_id)    REFERENCES stay (id)    ON DELETE CASCADE,
                CONSTRAINT fk_sth_group   FOREIGN KEY (group_id)   REFERENCES `group` (id) ON DELETE CASCADE,
                CONSTRAINT fk_sth_teacher FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('DROP TABLE stay_teacher_hours');
        $this->addSql('ALTER TABLE stay DROP COLUMN calculate_by_teacher');
    }
}
