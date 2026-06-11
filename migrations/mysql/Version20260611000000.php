<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sistema de ajustes: tablas setting_definition, global_setting_value, centre_setting_value, teacher_setting_value + ajustes iniciales (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        // ── Definiciones de ajustes ────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE setting_definition (
                id            BINARY(16)     NOT NULL,
                `key`         VARCHAR(100) NOT NULL,
                type          VARCHAR(10)  NOT NULL,
                default_value VARCHAR(255) NOT NULL,
                global_scope  TINYINT(1)   NOT NULL DEFAULT 0,
                centre_scope  TINYINT(1)   NOT NULL DEFAULT 0,
                teacher_scope TINYINT(1)   NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_setting_definition_key ON setting_definition (`key`)');

        // ── Valores globales ───────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE global_setting_value (
                id            BINARY(16)     NOT NULL,
                definition_id BINARY(16)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_global_setting_definition ON global_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_gsv_definition ON global_setting_value (definition_id)');
        $this->addSql('ALTER TABLE global_setting_value ADD CONSTRAINT FK_gsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');

        // ── Valores por centro ─────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE centre_setting_value (
                id            BINARY(16)     NOT NULL,
                definition_id BINARY(16)     NOT NULL,
                centre_id     BINARY(16)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_centre_setting_def_centre ON centre_setting_value (definition_id, centre_id)');
        $this->addSql('CREATE INDEX IDX_csv_definition ON centre_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_csv_centre     ON centre_setting_value (centre_id)');
        $this->addSql('ALTER TABLE centre_setting_value ADD CONSTRAINT FK_csv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');
        $this->addSql('ALTER TABLE centre_setting_value ADD CONSTRAINT FK_csv_centre     FOREIGN KEY (centre_id)     REFERENCES educational_centre(id)');

        // ── Valores por docente ────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE teacher_setting_value (
                id            BINARY(16)     NOT NULL,
                definition_id BINARY(16)     NOT NULL,
                teacher_id    BINARY(16)     NOT NULL,
                value         VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_setting_def_teacher ON teacher_setting_value (definition_id, teacher_id)');
        $this->addSql('CREATE INDEX IDX_tsv_definition ON teacher_setting_value (definition_id)');
        $this->addSql('CREATE INDEX IDX_tsv_teacher    ON teacher_setting_value (teacher_id)');
        $this->addSql('ALTER TABLE teacher_setting_value ADD CONSTRAINT FK_tsv_definition FOREIGN KEY (definition_id) REFERENCES setting_definition(id)');
        $this->addSql('ALTER TABLE teacher_setting_value ADD CONSTRAINT FK_tsv_teacher    FOREIGN KEY (teacher_id)    REFERENCES teacher(id)');

        // ── Ajustes iniciales (UNHEX(REPLACE(UUID(), '-', '')) genera UUIDs en MySQL / MariaDB) ─────────────────
        $this->addSql(<<<'SQL'
            INSERT INTO setting_definition (id, `key`, type, default_value, global_scope, centre_scope, teacher_scope) VALUES
                (UNHEX(REPLACE(UUID(), '-', '')), 'page.size',                             'integer', '20',   0, 0, 1),
                (UNHEX(REPLACE(UUID(), '-', '')), 'email.notifications',                   'boolean', 'true', 1, 1, 1),
                (UNHEX(REPLACE(UUID(), '-', '')), 'email.notification.tutor_assigned',     'boolean', 'true', 1, 1, 1),
                (UNHEX(REPLACE(UUID(), '-', '')), 'email.notification.positions_created',  'boolean', 'true', 1, 1, 1),
                (UNHEX(REPLACE(UUID(), '-', '')), 'email.notification.signature_reminder', 'boolean', 'true', 1, 1, 1)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE global_setting_value  DROP FOREIGN KEY FK_gsv_definition');
        $this->addSql('ALTER TABLE centre_setting_value  DROP FOREIGN KEY FK_csv_definition');
        $this->addSql('ALTER TABLE centre_setting_value  DROP FOREIGN KEY FK_csv_centre');
        $this->addSql('ALTER TABLE teacher_setting_value DROP FOREIGN KEY FK_tsv_definition');
        $this->addSql('ALTER TABLE teacher_setting_value DROP FOREIGN KEY FK_tsv_teacher');

        $this->addSql('DROP TABLE teacher_setting_value');
        $this->addSql('DROP TABLE centre_setting_value');
        $this->addSql('DROP TABLE global_setting_value');
        $this->addSql('DROP TABLE setting_definition');
    }
}
