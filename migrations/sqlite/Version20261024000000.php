<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajuste maintenance.enabled (modo mantenimiento), solo a nivel global (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
            (CAST(x'1A00000000004000800000000000001A' AS TEXT), 'maintenance.enabled', 'boolean', 'false', 1, 0, 0)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'maintenance.enabled')");
        $this->addSql("DELETE FROM setting_definition WHERE key = 'maintenance.enabled'");
    }
}
