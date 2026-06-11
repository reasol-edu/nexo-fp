<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
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
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // ── Definiciones de ajustes ────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE setting_definition (
                id            CHAR(36)     NOT NULL,
                key           VARCHAR(100) NOT NULL,
                type          VARCHAR(10)  NOT NULL,
                default_value VARCHAR(255) NOT NULL,
                global_scope  BOOLEAN      NOT NULL DEFAULT 0,
                centre_scope  BOOLEAN      NOT NULL DEFAULT 0,
                teacher_scope BOOLEAN      NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_setting_definition_key ON setting_definition (key)');

        // ── Valores globales ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE global_setting_value (
                id            CHAR(36)     NOT NULL,
                definition_id CHAR(36)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_gsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_global_setting_definition ON global_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_gsv_definition ON global_setting_value (definition_id)');

        // ── Valores por centro ─────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE centre_setting_value (
                id            CHAR(36)     NOT NULL,
                definition_id CHAR(36)     NOT NULL,
                centre_id     CHAR(36)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_csv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id),
                CONSTRAINT FK_csv_centre     FOREIGN KEY (centre_id)     REFERENCES educational_centre(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_centre_setting_def_centre ON centre_setting_value (definition_id, centre_id)');
        $this->addSql('CREATE INDEX IDX_csv_definition ON centre_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_csv_centre     ON centre_setting_value (centre_id)');

        // ── Valores por docente ────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE teacher_setting_value (
                id            CHAR(36)     NOT NULL,
                definition_id CHAR(36)     NOT NULL,
                teacher_id    CHAR(36)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_tsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id),
                CONSTRAINT FK_tsv_teacher    FOREIGN KEY (teacher_id)    REFERENCES teacher(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_setting_def_teacher ON teacher_setting_value (definition_id, teacher_id)');
        $this->addSql('CREATE INDEX IDX_tsv_definition ON teacher_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_tsv_teacher    ON teacher_setting_value (teacher_id)');

        // ── Ajustes iniciales ──────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, key, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
                ('1a000000-0000-4000-8000-000000000001', 'page.size',                             'integer', '20',   0, 0, 1),
                ('1a000000-0000-4000-8000-000000000002', 'email.notifications',                   'boolean', 'true', 1, 1, 1),
                ('1a000000-0000-4000-8000-000000000003', 'email.notification.tutor_assigned',     'boolean', 'true', 1, 1, 1),
                ('1a000000-0000-4000-8000-000000000004', 'email.notification.positions_created',  'boolean', 'true', 1, 1, 1),
                ('1a000000-0000-4000-8000-000000000005', 'email.notification.signature_reminder', 'boolean', 'true', 1, 1, 1)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP TABLE teacher_setting_value');
        $this->addSql('DROP TABLE centre_setting_value');
        $this->addSql('DROP TABLE global_setting_value');
        $this->addSql('DROP TABLE setting_definition');
    }
}
