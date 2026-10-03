<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Puestos con enseñanza preferente hasta una fecha: training_position.priority_programme_id y priority_until (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position ADD priority_programme_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE training_position ADD priority_until DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_tp_priority_programme ON training_position (priority_programme_id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_priority_programme FOREIGN KEY (priority_programme_id) REFERENCES programme (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position DROP CONSTRAINT FK_tp_priority_programme');
        $this->addSql('DROP INDEX IDX_tp_priority_programme');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_until');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_programme_id');
    }
}
