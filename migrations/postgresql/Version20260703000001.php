<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajuste email.notification.shared_position: aviso a las coordinaciones por cambios en puestos compartidos entre enseñanzas (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
                (gen_random_uuid(), 'email.notification.shared_position', 'boolean', 'true', TRUE, TRUE, TRUE)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql("DELETE FROM teacher_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM centre_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.notification.shared_position')");
        $this->addSql("DELETE FROM setting_definition WHERE key = 'email.notification.shared_position'");
    }
}
