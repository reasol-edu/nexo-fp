<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade training_position.signed_at con relleno aproximado desde stay.end_date (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE training_position ADD COLUMN signed_at DATETIME DEFAULT NULL');

        // Aproximación para el histórico: la fecha real de firma no se registraba
        $this->addSql(<<<'SQL'
            UPDATE training_position
            SET signed_at = (
                SELECT datetime(s.end_date)
                FROM stay s
                WHERE s.id = training_position.stay_id
            )
            WHERE signed = 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE training_position DROP COLUMN signed_at');
    }
}
