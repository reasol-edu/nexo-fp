<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Una estancia puede asociarse a varias enseñanzas: tabla stay_programme en lugar de stay.programme_id (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_programme (
                stay_id      UUID NOT NULL,
                programme_id UUID NOT NULL,
                PRIMARY KEY (stay_id, programme_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_sp_stay ON stay_programme (stay_id)');
        $this->addSql('CREATE INDEX IDX_sp_programme ON stay_programme (programme_id)');
        $this->addSql('ALTER TABLE stay_programme ADD CONSTRAINT fk_sp_stay FOREIGN KEY (stay_id) REFERENCES stay (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stay_programme ADD CONSTRAINT fk_sp_programme FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO stay_programme (stay_id, programme_id) SELECT id, programme_id FROM stay');

        $this->addSql('DROP INDEX IDX_stay_programme');
        $this->addSql('ALTER TABLE stay DROP COLUMN programme_id');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        // Al volver atrás solo se conserva una enseñanza por estancia (la primera por nombre).
        $this->addSql('ALTER TABLE stay ADD programme_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE stay SET programme_id = (
                SELECT sp.programme_id FROM stay_programme sp
                JOIN programme p ON p.id = sp.programme_id
                WHERE sp.stay_id = stay.id
                ORDER BY p.name, p.id
                LIMIT 1
            )
        SQL);
        $this->addSql('ALTER TABLE stay ALTER COLUMN programme_id SET NOT NULL');
        $this->addSql('CREATE INDEX IDX_stay_programme ON stay (programme_id)');
        $this->addSql('ALTER TABLE stay ADD CONSTRAINT FK_stay_programme FOREIGN KEY (programme_id) REFERENCES programme (id)');
        $this->addSql('DROP TABLE stay_programme');
    }
}
