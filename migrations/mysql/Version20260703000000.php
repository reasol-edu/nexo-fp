<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Una estancia puede asociarse a varias enseñanzas: tabla stay_programme en lugar de stay.programme_id (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_programme (
                stay_id      BINARY(16) NOT NULL,
                programme_id BINARY(16) NOT NULL,
                PRIMARY KEY (stay_id, programme_id),
                INDEX IDX_sp_stay (stay_id),
                INDEX IDX_sp_programme (programme_id),
                CONSTRAINT fk_sp_stay      FOREIGN KEY (stay_id)      REFERENCES stay (id)      ON DELETE CASCADE,
                CONSTRAINT fk_sp_programme FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE RESTRICT
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        $this->addSql('INSERT INTO stay_programme (stay_id, programme_id) SELECT id, programme_id FROM stay');

        // MySQL no permite borrar el índice mientras lo use la clave foránea: primero ésta.
        $this->addSql('ALTER TABLE stay DROP FOREIGN KEY FK_stay_programme');
        $this->addSql('ALTER TABLE stay DROP INDEX IDX_stay_programme');
        $this->addSql('ALTER TABLE stay DROP COLUMN programme_id');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        // Al volver atrás solo se conserva una enseñanza por estancia (la primera por nombre).
        $this->addSql('ALTER TABLE stay ADD programme_id BINARY(16) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE stay SET programme_id = (
                SELECT sp.programme_id FROM stay_programme sp
                JOIN programme p ON p.id = sp.programme_id
                WHERE sp.stay_id = stay.id
                ORDER BY p.name, p.id
                LIMIT 1
            )
        SQL);
        $this->addSql('ALTER TABLE stay MODIFY programme_id BINARY(16) NOT NULL');
        $this->addSql('CREATE INDEX IDX_stay_programme ON stay (programme_id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_stay_programme FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('DROP TABLE stay_programme');
    }
}
