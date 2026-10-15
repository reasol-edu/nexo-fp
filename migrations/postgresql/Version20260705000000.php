<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resumen diario de avisos por correo: tabla email_digest_item y ajustes email.digest y email.digest_hour (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE email_digest_item (
                id                    UUID         NOT NULL,
                educational_centre_id UUID         NOT NULL,
                recipient_id          UUID         NOT NULL,
                event_key             VARCHAR(50)  NOT NULL,
                params                JSON         NOT NULL,
                url                   VARCHAR(500) DEFAULT NULL,
                created_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                sent_at               TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_edi_recipient_sent ON email_digest_item (recipient_id, sent_at)');
        $this->addSql('CREATE INDEX idx_edi_centre ON email_digest_item (educational_centre_id)');

        $this->addSql('ALTER TABLE email_digest_item ADD CONSTRAINT fk_edi_centre    FOREIGN KEY (educational_centre_id) REFERENCES educational_centre (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email_digest_item ADD CONSTRAINT fk_edi_recipient FOREIGN KEY (recipient_id)          REFERENCES teacher (id)            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope, min_value, max_value) VALUES
                (gen_random_uuid(), 'email.digest',      'boolean', 'false', TRUE, FALSE, TRUE, NULL, NULL),
                (gen_random_uuid(), 'email.digest_hour', 'integer', '7',     TRUE, FALSE, TRUE, 0,    23)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        foreach (['teacher_setting_value', 'centre_setting_value', 'global_setting_value'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE definition_id IN (SELECT id FROM setting_definition WHERE key IN ('email.digest', 'email.digest_hour'))");
        }
        $this->addSql("DELETE FROM setting_definition WHERE key IN ('email.digest', 'email.digest_hour')");
        $this->addSql('DROP TABLE email_digest_item');
    }
}
