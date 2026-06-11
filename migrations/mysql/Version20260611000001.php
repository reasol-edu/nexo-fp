<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verificación de email: campos pending_email, email_verification_token y email_verification_token_expires_at en teacher (MySQL 8)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQL80Platform,
            'Esta migración sólo puede ejecutarse en MySQL 8.'
        );

        $this->addSql('ALTER TABLE teacher ADD pending_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE teacher ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        // COMMENT es la forma de MySQL de almacenar metadatos de tipo Doctrine
        $this->addSql("ALTER TABLE teacher ADD email_verification_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teacher_email_verification_token ON teacher (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQL80Platform,
            'Esta migración sólo puede ejecutarse en MySQL 8.'
        );

        $this->addSql('DROP INDEX UNIQ_teacher_email_verification_token ON teacher');
        $this->addSql('ALTER TABLE teacher DROP COLUMN email_verification_token_expires_at');
        $this->addSql('ALTER TABLE teacher DROP COLUMN email_verification_token');
        $this->addSql('ALTER TABLE teacher DROP COLUMN pending_email');
    }
}
