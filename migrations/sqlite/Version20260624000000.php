<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recordatorio de firma: columna stay.last_signature_reminder_sent_at + ajuste email.notification.signature_reminder.days (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE stay ADD COLUMN last_signature_reminder_sent_at DATETIME DEFAULT NULL');

        // CAST(x'...' AS TEXT): mismo estilo de UUID que el resto de ajustes sembrados.
        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A000000000040008000000000000006' AS TEXT), 'email.notification.signature_reminder.days', 'integer', '7', 1, 1, 0, 1, 365)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("DELETE FROM setting_definition WHERE key = 'email.notification.signature_reminder.days'");
        $this->addSql('ALTER TABLE stay DROP COLUMN last_signature_reminder_sent_at');
    }
}
