<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'La preferencia temporal de un puesto pasa de la enseñanza al nivel: priority_programme_year_id (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position ADD priority_programme_year_id UUID DEFAULT NULL');

        // La enseñanza preferente pasa a ser su primer nivel (por nombre) entre los que ofrece el puesto; si el puesto
        // no ofrece ninguno de esa enseñanza, el primer nivel de la enseñanza. Si la enseñanza no tiene niveles, la
        // preferencia se descarta (sin nivel no se puede aplicar).
        $this->addSql(<<<'SQL'
            UPDATE training_position SET priority_programme_year_id = (
                SELECT py.id FROM programme_year py
                JOIN training_position_programme_year tppy ON tppy.programme_year_id = py.id
                WHERE tppy.training_position_id = training_position.id AND py.programme_id = training_position.priority_programme_id
                ORDER BY py.name, py.id
                LIMIT 1
            ) WHERE priority_programme_id IS NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE training_position SET priority_programme_year_id = (
                SELECT py.id FROM programme_year py
                WHERE py.programme_id = training_position.priority_programme_id
                ORDER BY py.name, py.id
                LIMIT 1
            ) WHERE priority_programme_id IS NOT NULL AND priority_programme_year_id IS NULL
        SQL);
        $this->addSql('UPDATE training_position SET priority_until = NULL WHERE priority_programme_year_id IS NULL');

        $this->addSql('ALTER TABLE training_position DROP CONSTRAINT FK_tp_priority_programme');
        $this->addSql('DROP INDEX IDX_tp_priority_programme');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_programme_id');

        $this->addSql('CREATE INDEX IDX_A149F7627CC7E3B7 ON training_position (priority_programme_year_id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_A149F7627CC7E3B7 FOREIGN KEY (priority_programme_year_id) REFERENCES programme_year (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position ADD priority_programme_id UUID DEFAULT NULL');
        $this->addSql('UPDATE training_position SET priority_programme_id = (SELECT py.programme_id FROM programme_year py WHERE py.id = training_position.priority_programme_year_id) WHERE priority_programme_year_id IS NOT NULL');

        $this->addSql('ALTER TABLE training_position DROP CONSTRAINT FK_A149F7627CC7E3B7');
        $this->addSql('DROP INDEX IDX_A149F7627CC7E3B7');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_programme_year_id');

        $this->addSql('CREATE INDEX IDX_tp_priority_programme ON training_position (priority_programme_id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_priority_programme FOREIGN KEY (priority_programme_id) REFERENCES programme (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
