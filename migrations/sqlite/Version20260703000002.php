<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Puestos con enseñanza preferente hasta una fecha: training_position.priority_programme_id y priority_until (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE training_position ADD COLUMN priority_programme_id CHAR(36) DEFAULT NULL REFERENCES programme(id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE training_position ADD COLUMN priority_until DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_tp_priority_programme ON training_position (priority_programme_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP INDEX IDX_tp_priority_programme');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_until');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_programme_id');
    }
}
