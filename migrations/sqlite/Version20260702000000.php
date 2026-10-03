<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de correos enviados: tabla email_notification_log y ajuste email.log_retention_days (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE email_notification_log (
                id                    INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
                educational_centre_id CLOB         DEFAULT NULL,
                recipient_id          CLOB         DEFAULT NULL,
                recipient_name        VARCHAR(200) NOT NULL,
                recipient_email       VARCHAR(255) NOT NULL,
                event_key             VARCHAR(50)  NOT NULL,
                subject               VARCHAR(255) NOT NULL,
                success               BOOLEAN      NOT NULL,
                error_message         CLOB         DEFAULT NULL,
                sent_at               DATETIME     NOT NULL,
                CONSTRAINT fk_enl_centre    FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id) ON DELETE CASCADE,
                CONSTRAINT fk_enl_recipient FOREIGN KEY (recipient_id)          REFERENCES teacher (id)            ON DELETE SET NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_enl_centre_sent ON email_notification_log (educational_centre_id, sent_at)');
        $this->addSql('CREATE INDEX idx_enl_sent        ON email_notification_log (sent_at)');
        $this->addSql('CREATE INDEX idx_enl_event       ON email_notification_log (event_key)');

        // CAST(x'...' AS TEXT): mismo estilo de UUID que el resto de ajustes sembrados.
        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A000000000040008000000000000009' AS TEXT), 'email.log_retention_days', 'integer', '90', 1, 0, 0, 0, 3650)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE key = 'email.log_retention_days')");
        $this->addSql("DELETE FROM setting_definition WHERE key = 'email.log_retention_days'");
        $this->addSql('DROP TABLE email_notification_log');
    }
}
