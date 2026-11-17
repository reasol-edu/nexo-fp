<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajustes globales de las copias de seguridad automáticas: activación, copias que se conservan y espacio máximo (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // CAST(x'...' AS TEXT): mismo estilo de UUID que el resto de ajustes sembrados.
        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A00000000004000800000000000001B' AS TEXT), 'backup.auto_enabled', 'boolean', 'false', 1, 0, 0, NULL, NULL),
            (CAST(x'1A00000000004000800000000000001C' AS TEXT), 'backup.auto_keep_daily', 'integer', '7', 1, 0, 0, 1, 60),
            (CAST(x'1A00000000004000800000000000001D' AS TEXT), 'backup.auto_keep_weekly', 'integer', '4', 1, 0, 0, 0, 52),
            (CAST(x'1A00000000004000800000000000001E' AS TEXT), 'backup.auto_keep_monthly', 'integer', '3', 1, 0, 0, 0, 36),
            (CAST(x'1A00000000004000800000000000001F' AS TEXT), 'backup.auto_max_size_mb', 'integer', '0', 1, 0, 0, 0, 1048576)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $keys = "'backup.auto_enabled', 'backup.auto_keep_daily', 'backup.auto_keep_weekly', 'backup.auto_keep_monthly', 'backup.auto_max_size_mb'";
        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ($keys))");
        $this->addSql("DELETE FROM setting_definition WHERE key IN ($keys)");
    }
}
