<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de actividad: tabla activity_log (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id               INTEGER         NOT NULL PRIMARY KEY AUTOINCREMENT,
                active_user_id   CLOB            DEFAULT NULL,
                real_user_id     CLOB            DEFAULT NULL,
                academic_year_id CLOB            DEFAULT NULL,
                created_at       DATETIME        NOT NULL,
                ip               VARCHAR(45)     NOT NULL,
                action_type      VARCHAR(100)    NOT NULL,
                data             CLOB            DEFAULT NULL,
                CONSTRAINT fk_al_active_user   FOREIGN KEY (active_user_id)   REFERENCES teacher (id) ON DELETE SET NULL,
                CONSTRAINT fk_al_real_user     FOREIGN KEY (real_user_id)     REFERENCES teacher (id) ON DELETE SET NULL,
                CONSTRAINT fk_al_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE SET NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_al_created      ON activity_log (created_at)');
        $this->addSql('CREATE INDEX idx_al_user_created ON activity_log (active_user_id, created_at)');
        $this->addSql('CREATE INDEX idx_al_type_created ON activity_log (action_type, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP TABLE activity_log');
    }
}
