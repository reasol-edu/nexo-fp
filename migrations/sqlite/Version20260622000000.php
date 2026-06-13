<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea la tabla messenger_messages para el transporte async (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('CREATE TABLE messenger_messages (
            id INTEGER NOT NULL,
            body CLOB NOT NULL,
            headers CLOB NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            ,
            available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            ,
            delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            ,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP TABLE messenger_messages');
    }
}
