<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Puestos con enseñanza preferente hasta una fecha: training_position.priority_programme_id y priority_until (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE training_position ADD priority_programme_id BINARY(16) DEFAULT NULL, ADD priority_until DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_tp_priority_programme ON training_position (priority_programme_id)');
        $this->addSql('ALTER TABLE training_position ADD CONSTRAINT FK_tp_priority_programme FOREIGN KEY (priority_programme_id) REFERENCES programme (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE training_position DROP FOREIGN KEY FK_tp_priority_programme');
        $this->addSql('DROP INDEX IDX_tp_priority_programme ON training_position');
        $this->addSql('ALTER TABLE training_position DROP COLUMN priority_until, DROP COLUMN priority_programme_id');
    }
}
