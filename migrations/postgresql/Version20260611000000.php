<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sistema de ajustes: tablas setting_definition, global_setting_value, centre_setting_value, teacher_setting_value + ajustes iniciales';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        // ── Definiciones de ajustes ────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE setting_definition (
                id            UUID         NOT NULL,
                key           VARCHAR(100) NOT NULL,
                type          VARCHAR(10)  NOT NULL,
                default_value VARCHAR(255) NOT NULL,
                global_scope  BOOLEAN      NOT NULL DEFAULT FALSE,
                centre_scope  BOOLEAN      NOT NULL DEFAULT FALSE,
                teacher_scope BOOLEAN      NOT NULL DEFAULT FALSE,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_setting_definition_key ON setting_definition (key)');

        // ── Valores globales ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE global_setting_value (
                id            UUID         NOT NULL,
                definition_id UUID         NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_global_setting_definition ON global_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_gsv_definition ON global_setting_value (definition_id)');
        $this->addSql('ALTER TABLE global_setting_value ADD CONSTRAINT FK_gsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');

        // ── Valores por centro ─────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE centre_setting_value (
                id            UUID         NOT NULL,
                definition_id UUID         NOT NULL,
                centre_id     UUID         NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_centre_setting_def_centre ON centre_setting_value (definition_id, centre_id)');
        $this->addSql('CREATE INDEX IDX_csv_definition ON centre_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_csv_centre     ON centre_setting_value (centre_id)');
        $this->addSql('ALTER TABLE centre_setting_value ADD CONSTRAINT FK_csv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');
        $this->addSql('ALTER TABLE centre_setting_value ADD CONSTRAINT FK_csv_centre     FOREIGN KEY (centre_id)     REFERENCES educational_centre(id)');

        // ── Valores por docente ────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE teacher_setting_value (
                id            UUID         NOT NULL,
                definition_id UUID         NOT NULL,
                teacher_id    UUID         NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_setting_def_teacher ON teacher_setting_value (definition_id, teacher_id)');
        $this->addSql('CREATE INDEX IDX_tsv_definition ON teacher_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_tsv_teacher    ON teacher_setting_value (teacher_id)');
        $this->addSql('ALTER TABLE teacher_setting_value ADD CONSTRAINT FK_tsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');
        $this->addSql('ALTER TABLE teacher_setting_value ADD CONSTRAINT FK_tsv_teacher    FOREIGN KEY (teacher_id)    REFERENCES teacher(id)');

        // ── Ajustes iniciales ──────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
                (gen_random_uuid(), 'page.size',                              'integer', '20',   FALSE, FALSE, TRUE),
                (gen_random_uuid(), 'email.notifications',                    'boolean', 'true', TRUE,  TRUE,  TRUE),
                (gen_random_uuid(), 'email.notification.tutor_assigned',      'boolean', 'true', TRUE,  TRUE,  TRUE),
                (gen_random_uuid(), 'email.notification.positions_created',   'boolean', 'true', TRUE,  TRUE,  TRUE),
                (gen_random_uuid(), 'email.notification.signature_reminder',  'boolean', 'true', TRUE,  TRUE,  TRUE)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE global_setting_value  DROP CONSTRAINT FK_gsv_definition');
        $this->addSql('ALTER TABLE centre_setting_value  DROP CONSTRAINT FK_csv_definition');
        $this->addSql('ALTER TABLE centre_setting_value  DROP CONSTRAINT FK_csv_centre');
        $this->addSql('ALTER TABLE teacher_setting_value DROP CONSTRAINT FK_tsv_definition');
        $this->addSql('ALTER TABLE teacher_setting_value DROP CONSTRAINT FK_tsv_teacher');

        $this->addSql('DROP TABLE teacher_setting_value');
        $this->addSql('DROP TABLE centre_setting_value');
        $this->addSql('DROP TABLE global_setting_value');
        $this->addSql('DROP TABLE setting_definition');
    }
}
