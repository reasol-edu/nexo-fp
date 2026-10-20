<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ficha del estudiante por curso: tabla student_year_profile (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE student_year_profile (
                id                      UUID         NOT NULL,
                student_id              UUID         NOT NULL,
                academic_year_id        UUID         NOT NULL,
                has_nuss                BOOLEAN      DEFAULT NULL,
                takes_ffeoe             BOOLEAN      NOT NULL DEFAULT TRUE,
                ffeoe_exclusion_reason  TEXT         DEFAULT NULL,
                has_car                 BOOLEAN      NOT NULL DEFAULT FALSE,
                brings_company          BOOLEAN      NOT NULL DEFAULT FALSE,
                brought_company_name    VARCHAR(255) DEFAULT NULL,
                attitude                SMALLINT     DEFAULT NULL,
                residence_city          VARCHAR(255) DEFAULT NULL,
                preferred_city          VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_syp_student_year ON student_year_profile (student_id, academic_year_id)');
        $this->addSql('CREATE INDEX idx_syp_year ON student_year_profile (academic_year_id)');
        $this->addSql('ALTER TABLE student_year_profile ADD CONSTRAINT fk_syp_student FOREIGN KEY (student_id)       REFERENCES student (id)       ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE student_year_profile ADD CONSTRAINT fk_syp_year    FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('DROP TABLE student_year_profile');
    }
}
