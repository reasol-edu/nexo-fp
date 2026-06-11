<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bloqueo de ajustes: campo locked en global_setting_value y centre_setting_value (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE global_setting_value ADD locked BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE centre_setting_value ADD locked BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE global_setting_value DROP locked');
        $this->addSql('ALTER TABLE centre_setting_value DROP locked');
    }
}
