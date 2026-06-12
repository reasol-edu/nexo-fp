<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade training_position.signed_at con relleno aproximado desde stay.end_date (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE training_position ADD signed_at DATETIME DEFAULT NULL');

        // Aproximación para el histórico: la fecha real de firma no se registraba
        $this->addSql(<<<'SQL'
            UPDATE training_position tp
            JOIN stay s ON s.id = tp.stay_id
            SET tp.signed_at = TIMESTAMP(s.end_date)
            WHERE tp.signed = 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE training_position DROP COLUMN signed_at');
    }
}
