<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajuste security.idle_timeout_minutes: cierre de sesión por inactividad, solo a nivel global (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // CAST(x'...' AS TEXT): mismo estilo de UUID que el resto de ajustes sembrados.
        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A000000000040008000000000000008' AS TEXT), 'security.idle_timeout_minutes', 'integer', '120', 1, 0, 0, 0, 1440)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'security.idle_timeout_minutes')");
        $this->addSql("DELETE FROM setting_definition WHERE key = 'security.idle_timeout_minutes'");
    }
}
