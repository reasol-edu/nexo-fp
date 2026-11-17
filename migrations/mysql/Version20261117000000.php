<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajustes globales de las copias de seguridad automáticas: activación, copias que se conservan y espacio máximo (MySQL/MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, `key`, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (UNHEX(REPLACE(UUID(), '-', '')), 'backup.auto_enabled', 'boolean', 'false', 1, 0, 0, NULL, NULL),
                (UNHEX(REPLACE(UUID(), '-', '')), 'backup.auto_keep_daily', 'integer', '7', 1, 0, 0, 1, 60),
                (UNHEX(REPLACE(UUID(), '-', '')), 'backup.auto_keep_weekly', 'integer', '4', 1, 0, 0, 0, 52),
                (UNHEX(REPLACE(UUID(), '-', '')), 'backup.auto_keep_monthly', 'integer', '3', 1, 0, 0, 0, 36),
                (UNHEX(REPLACE(UUID(), '-', '')), 'backup.auto_max_size_mb', 'integer', '0', 1, 0, 0, 0, 1048576)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $keys = "'backup.auto_enabled', 'backup.auto_keep_daily', 'backup.auto_keep_weekly', 'backup.auto_keep_monthly', 'backup.auto_max_size_mb'";
        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE `key` IN ($keys))");
        $this->addSql("DELETE FROM setting_definition WHERE `key` IN ($keys)");
    }
}
