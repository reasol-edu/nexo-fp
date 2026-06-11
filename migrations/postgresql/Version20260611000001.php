<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('ALTER TABLE teacher ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD email_verification_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN teacher.email_verification_token_expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_email_verification_token ON teacher (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Esta migración sólo puede ejecutarse en PostgreSQL.'
        );

        $this->addSql('DROP INDEX UNIQ_teacher_email_verification_token');
        $this->addSql('ALTER TABLE teacher DROP pending_email');
        $this->addSql('ALTER TABLE teacher DROP email_verification_token');
        $this->addSql('ALTER TABLE teacher DROP email_verification_token_expires_at');
    }
}
