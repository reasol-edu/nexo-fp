<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resumen diario de avisos por correo: tabla email_digest_item y ajustes email.digest y email.digest_hour (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE email_digest_item (
                id                    CHAR(36)     NOT NULL,
                educational_centre_id CHAR(36)     NOT NULL,
                recipient_id          CHAR(36)     NOT NULL,
                event_key             VARCHAR(50)  NOT NULL,
                params                CLOB         NOT NULL,
                url                   VARCHAR(500) DEFAULT NULL,
                created_at            DATETIME     NOT NULL,
                sent_at               DATETIME     DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_edi_centre    FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id) ON DELETE CASCADE,
                CONSTRAINT fk_edi_recipient FOREIGN KEY (recipient_id)          REFERENCES teacher (id)            ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_edi_recipient_sent ON email_digest_item (recipient_id, sent_at)');
        $this->addSql('CREATE INDEX idx_edi_centre ON email_digest_item (educational_centre_id)');

        $this->addSql("INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
            (CAST(x'1A00000000004000800000000000000C' AS TEXT), 'email.digest',      'boolean', 'false', 1, 0, 1, NULL, NULL),
            (CAST(x'1A00000000004000800000000000000D' AS TEXT), 'email.digest_hour', 'integer', '7',     1, 0, 1, 0,    23)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        foreach (['teacher_setting_value', 'centre_setting_value', 'global_setting_value'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ('email.digest', 'email.digest_hour'))");
        }
        $this->addSql("DELETE FROM setting_definition WHERE key IN ('email.digest', 'email.digest_hour')");
        $this->addSql('DROP TABLE email_digest_item');
    }
}
