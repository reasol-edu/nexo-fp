<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajuste email.notification.shared_position: aviso a las coordinaciones por cambios en puestos compartidos entre enseñanzas (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
            (CAST(x'1A00000000004000800000000000000A' AS TEXT), 'email.notification.shared_position', 'boolean', 'true', 1, 1, 1)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("DELETE FROM teacher_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM centre_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM setting_definition WHERE key = 'email.notification.shared_position'");
    }
}
