<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recordatorio de firma: columna stay.last_signature_reminder_sent_at + ajuste email.notification.signature_reminder.days (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE stay ADD last_signature_reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (gen_random_uuid(), 'email.notification.signature_reminder.days', 'integer', '7', TRUE, TRUE, FALSE, 1, 365)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql("DELETE FROM setting_definition WHERE key = 'email.notification.signature_reminder.days'");
        $this->addSql('ALTER TABLE stay DROP last_signature_reminder_sent_at');
    }
}
