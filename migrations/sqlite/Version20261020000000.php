<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ficha del estudiante por curso: tabla student_year_profile (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE student_year_profile (
                id                      CHAR(36)     NOT NULL,
                student_id              CHAR(36)     NOT NULL,
                academic_year_id        CHAR(36)     NOT NULL,
                has_nuss                BOOLEAN      DEFAULT NULL,
                takes_ffeoe             BOOLEAN      NOT NULL DEFAULT 1,
                ffeoe_exclusion_reason  CLOB         DEFAULT NULL,
                has_car                 BOOLEAN      NOT NULL DEFAULT 0,
                brings_company          BOOLEAN      NOT NULL DEFAULT 0,
                brought_company_name    VARCHAR(255) DEFAULT NULL,
                attitude                SMALLINT     DEFAULT NULL,
                residence_city          VARCHAR(255) DEFAULT NULL,
                preferred_city          VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_syp_student FOREIGN KEY (student_id)       REFERENCES student (id)       ON DELETE CASCADE,
                CONSTRAINT fk_syp_year    FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_syp_student_year ON student_year_profile (student_id, academic_year_id)');
        $this->addSql('CREATE INDEX idx_syp_year ON student_year_profile (academic_year_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP TABLE student_year_profile');
    }
}
