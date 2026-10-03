<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de correos enviados: tabla email_notification_log y ajuste email.log_retention_days (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE email_notification_log (
                id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                educational_centre_id BINARY(16)   DEFAULT NULL,
                recipient_id          BINARY(16)   DEFAULT NULL,
                recipient_name        VARCHAR(200) NOT NULL,
                recipient_email       VARCHAR(255) NOT NULL,
                event_key             VARCHAR(50)  NOT NULL,
                subject               VARCHAR(255) NOT NULL,
                success               TINYINT(1)   NOT NULL,
                error_message         LONGTEXT     DEFAULT NULL,
                sent_at               DATETIME     NOT NULL,
                PRIMARY KEY (id),
                INDEX idx_enl_centre_sent (educational_centre_id, sent_at),
                INDEX idx_enl_sent        (sent_at),
                INDEX idx_enl_event       (event_key),
                CONSTRAINT fk_enl_centre    FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id) ON DELETE CASCADE,
                CONSTRAINT fk_enl_recipient FOREIGN KEY (recipient_id)          REFERENCES teacher (id)            ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, `key`, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (UNHEX(REPLACE(UUID(), '-', '')), 'email.log_retention_days', 'integer', '90', 1, 0, 0, 0, 3650)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql("DELETE FROM global_setting_value WHERE definition_id IN (SELECT id FROM setting_definition WHERE `key` = 'email.log_retention_days')");
        $this->addSql("DELETE FROM setting_definition WHERE `key` = 'email.log_retention_days'");
        $this->addSql('DROP TABLE email_notification_log');
    }
}
