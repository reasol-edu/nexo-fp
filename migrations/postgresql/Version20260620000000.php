<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade training_position.signed_at con relleno aproximado desde stay.end_date (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position ADD signed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Aproximación para el histórico: la fecha real de firma no se registraba
        $this->addSql(<<<'SQL'
            UPDATE training_position tp
            SET signed_at = s.end_date::timestamp
            FROM stay s
            WHERE s.id = tp.stay_id AND tp.signed = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE training_position DROP COLUMN signed_at');
    }
}
