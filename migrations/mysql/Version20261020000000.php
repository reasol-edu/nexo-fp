<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ficha del estudiante por curso: tabla student_year_profile (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE student_year_profile (
                id                      BINARY(16)   NOT NULL,
                student_id              BINARY(16)   NOT NULL,
                academic_year_id        BINARY(16)   NOT NULL,
                has_nuss                TINYINT(1)   DEFAULT NULL,
                takes_ffeoe             TINYINT(1)   NOT NULL DEFAULT 1,
                ffeoe_exclusion_reason  LONGTEXT     DEFAULT NULL,
                has_car                 TINYINT(1)   NOT NULL DEFAULT 0,
                brings_company          TINYINT(1)   NOT NULL DEFAULT 0,
                brought_company_name    VARCHAR(255) DEFAULT NULL,
                attitude                SMALLINT     DEFAULT NULL,
                residence_city          VARCHAR(255) DEFAULT NULL,
                preferred_city          VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX uq_syp_student_year (student_id, academic_year_id),
                INDEX idx_syp_year (academic_year_id),
                CONSTRAINT fk_syp_student FOREIGN KEY (student_id)       REFERENCES student (id)       ON DELETE CASCADE,
                CONSTRAINT fk_syp_year    FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('DROP TABLE student_year_profile');
    }
}
