<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verificación de email: campos pending_email, email_verification_token y email_verification_token_expires_at en teacher';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql('ALTER TABLE teacher ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD email_verification_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // SQLite no soporta DROP COLUMN en versiones antiguas; recrear la tabla si es necesario
        $this->addSql('CREATE TEMPORARY TABLE teacher_backup AS SELECT * FROM teacher');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('CREATE TABLE teacher AS SELECT * FROM teacher_backup');
        $this->addSql('DROP TABLE teacher_backup');
    }
}
