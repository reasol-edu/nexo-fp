<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de actividad: tabla activity_log (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id              SERIAL          NOT NULL,
                active_user_id  UUID            DEFAULT NULL,
                real_user_id    UUID            DEFAULT NULL,
                academic_year_id UUID           DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                ip              VARCHAR(45)     NOT NULL,
                action_type     VARCHAR(100)    NOT NULL,
                data            JSON            DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_al_created      ON activity_log (created_at)');
        $this->addSql('CREATE INDEX idx_al_user_created ON activity_log (active_user_id, created_at)');
        $this->addSql('CREATE INDEX idx_al_type_created ON activity_log (action_type, created_at)');

        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT fk_al_active_user  FOREIGN KEY (active_user_id)   REFERENCES teacher (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT fk_al_real_user    FOREIGN KEY (real_user_id)     REFERENCES teacher (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT fk_al_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("COMMENT ON COLUMN activity_log.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('DROP TABLE activity_log');
    }
}
