<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bloqueo de ajustes: campo locked en global_setting_value y centre_setting_value (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE global_setting_value ADD COLUMN locked INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE centre_setting_value ADD COLUMN locked INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE global_setting_value DROP COLUMN locked');
        $this->addSql('ALTER TABLE centre_setting_value DROP COLUMN locked');
    }
}
