<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cálculo de asignaciones por docente: stay.calculate_by_teacher y tabla stay_teacher_hours (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE stay ADD calculate_by_teacher BOOLEAN NOT NULL DEFAULT FALSE');

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_teacher_hours (
                id         UUID    NOT NULL,
                stay_id    UUID    NOT NULL,
                group_id   UUID    NOT NULL,
                teacher_id UUID    NOT NULL,
                hours      INTEGER NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uq_sth ON stay_teacher_hours (stay_id, group_id, teacher_id)');
        $this->addSql('CREATE INDEX idx_sth_group ON stay_teacher_hours (group_id)');
        $this->addSql('CREATE INDEX idx_sth_teacher ON stay_teacher_hours (teacher_id)');

        $this->addSql('ALTER TABLE stay_teacher_hours ADD CONSTRAINT fk_sth_stay    FOREIGN KEY (stay_id)    REFERENCES stay (id)    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stay_teacher_hours ADD CONSTRAINT fk_sth_group   FOREIGN KEY (group_id)   REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stay_teacher_hours ADD CONSTRAINT fk_sth_teacher FOREIGN KEY (teacher_id) REFERENCES teacher (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('DROP TABLE stay_teacher_hours');
        $this->addSql('ALTER TABLE stay DROP calculate_by_teacher');
    }
}
