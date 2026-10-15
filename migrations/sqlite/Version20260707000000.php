<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajustes attention.urgent_days y attention.soon_days: umbrales de urgencia del inicio y de los avisos (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A00000000004000800000000000000E' AS TEXT), 'attention.urgent_days', 'integer', '7',  1, 1, 0, 1, 60),
            (CAST(x'1A00000000004000800000000000000F' AS TEXT), 'attention.soon_days',   'integer', '21', 1, 1, 0, 2, 180)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        foreach (['centre_setting_value', 'global_setting_value'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ('attention.urgent_days', 'attention.soon_days'))");
        }
        $this->addSql("DELETE FROM setting_definition WHERE key IN ('attention.urgent_days', 'attention.soon_days')");
    }
}
