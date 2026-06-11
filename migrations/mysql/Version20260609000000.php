<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Múltiples tutores por grupo: convierte tutor_id en tabla group_tutor (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE group_tutor (
                group_id   BINARY(16) NOT NULL,
                teacher_id BINARY(16) NOT NULL,
                PRIMARY KEY(group_id, teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
        $this->addSql('CREATE INDEX IDX_gtu_group   ON group_tutor (group_id)');
        $this->addSql('CREATE INDEX IDX_gtu_teacher ON group_tutor (teacher_id)');
        $this->addSql('ALTER TABLE group_tutor ADD CONSTRAINT FK_gtu_group   FOREIGN KEY (group_id)   REFERENCES `group`(id)');
        $this->addSql('ALTER TABLE group_tutor ADD CONSTRAINT FK_gtu_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id)');

        $this->addSql('INSERT INTO group_tutor (group_id, teacher_id) SELECT id, tutor_id FROM `group` WHERE tutor_id IS NOT NULL');

        // MySQL 8 soporta DROP FOREIGN KEY + DROP INDEX + DROP COLUMN en un solo ALTER (también válido en MariaDB)
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_group_tutor');
        $this->addSql('ALTER TABLE `group` DROP INDEX IDX_group_tutor');
        $this->addSql('ALTER TABLE `group` DROP COLUMN tutor_id');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql('ALTER TABLE `group` ADD COLUMN tutor_id BINARY(16) DEFAULT NULL');
        $this->addSql('UPDATE `group` g SET tutor_id = (SELECT teacher_id FROM group_tutor WHERE group_id = g.id LIMIT 1)');
        $this->addSql('CREATE INDEX IDX_group_tutor ON `group` (tutor_id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_group_tutor FOREIGN KEY (tutor_id) REFERENCES teacher(id)');

        $this->addSql('ALTER TABLE group_tutor DROP FOREIGN KEY FK_gtu_group');
        $this->addSql('ALTER TABLE group_tutor DROP FOREIGN KEY FK_gtu_teacher');
        $this->addSql('DROP TABLE group_tutor');
    }
}
