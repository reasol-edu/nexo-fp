<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade teacher.password_reset_token y password_reset_token_expires_at (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE teacher ADD COLUMN password_reset_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD COLUMN password_reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B0F6A6D51893F1B9 ON teacher (password_reset_token)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('DROP INDEX UNIQ_B0F6A6D51893F1B9');
        $this->addSql('ALTER TABLE teacher DROP COLUMN password_reset_token');
        $this->addSql('ALTER TABLE teacher DROP COLUMN password_reset_token_expires_at');
    }
}
