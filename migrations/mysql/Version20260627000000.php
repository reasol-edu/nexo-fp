<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registro de actividad: tabla activity_log (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                active_user_id   CHAR(36)         DEFAULT NULL COMMENT '(DC2Type:uuid)',
                real_user_id     CHAR(36)         DEFAULT NULL COMMENT '(DC2Type:uuid)',
                academic_year_id CHAR(36)         DEFAULT NULL COMMENT '(DC2Type:uuid)',
                created_at       DATETIME         NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                ip               VARCHAR(45)      NOT NULL,
                action_type      VARCHAR(100)     NOT NULL,
                data             JSON             DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_al_created       (created_at),
                INDEX idx_al_user_created  (active_user_id, created_at),
                INDEX idx_al_type_created  (action_type, created_at),
                CONSTRAINT fk_al_active_user   FOREIGN KEY (active_user_id)   REFERENCES teacher (id) ON DELETE SET NULL,
                CONSTRAINT fk_al_real_user     FOREIGN KEY (real_user_id)     REFERENCES teacher (id) ON DELETE SET NULL,
                CONSTRAINT fk_al_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_year (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('DROP TABLE activity_log');
    }
}
