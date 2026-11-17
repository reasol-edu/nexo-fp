<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajustes globales de las copias de seguridad automáticas: activación, copias que se conservan y espacio máximo (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (gen_random_uuid(), 'backup.auto_enabled', 'boolean', 'false', TRUE, FALSE, FALSE, NULL, NULL),
                (gen_random_uuid(), 'backup.auto_keep_daily', 'integer', '7', TRUE, FALSE, FALSE, 1, 60),
                (gen_random_uuid(), 'backup.auto_keep_weekly', 'integer', '4', TRUE, FALSE, FALSE, 0, 52),
                (gen_random_uuid(), 'backup.auto_keep_monthly', 'integer', '3', TRUE, FALSE, FALSE, 0, 36),
                (gen_random_uuid(), 'backup.auto_max_size_mb', 'integer', '0', TRUE, FALSE, FALSE, 0, 1048576)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $keys = "'backup.auto_enabled', 'backup.auto_keep_daily', 'backup.auto_keep_weekly', 'backup.auto_keep_monthly', 'backup.auto_max_size_mb'";
        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ($keys))");
        $this->addSql("DELETE FROM setting_definition WHERE key IN ($keys)");
    }
}
