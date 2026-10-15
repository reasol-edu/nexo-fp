<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajustes attention.urgent_days y attention.soon_days: umbrales de urgencia del inicio y de los avisos (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (gen_random_uuid(), 'attention.urgent_days', 'integer', '7',  TRUE, TRUE, FALSE, 1, 60),
                (gen_random_uuid(), 'attention.soon_days',   'integer', '21', TRUE, TRUE, FALSE, 2, 180)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        foreach (['centre_setting_value', 'global_setting_value'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ('attention.urgent_days', 'attention.soon_days'))");
        }
        $this->addSql("DELETE FROM setting_definition WHERE key IN ('attention.urgent_days', 'attention.soon_days')");
    }
}
