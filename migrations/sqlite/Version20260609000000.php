<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Múltiples tutores por grupo: convierte tutor_id en tabla group_tutor (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE group_tutor (
                group_id   CHAR(36) NOT NULL,
                teacher_id CHAR(36) NOT NULL,
                PRIMARY KEY(group_id, teacher_id),
                CONSTRAINT FK_gtu_group   FOREIGN KEY (group_id)   REFERENCES "group"(id),
                CONSTRAINT FK_gtu_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id)
            )
        SQL);
        $this->addSql('INSERT INTO group_tutor (group_id, teacher_id) SELECT id, tutor_id FROM "group" WHERE tutor_id IS NOT NULL');

        // SQLite no permite DROP COLUMN cuando hay FK activas; se recrea la tabla sin tutor_id
        $this->addSql(<<<'SQL'
            CREATE TABLE "group_new" (
                id                CHAR(36)     NOT NULL,
                programme_year_id CHAR(36)     NOT NULL,
                name              VARCHAR(255) NOT NULL,
                details           TEXT         DEFAULT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_group_py FOREIGN KEY (programme_year_id) REFERENCES programme_year(id)
            )
        SQL);
        $this->addSql('INSERT INTO "group_new" SELECT id, programme_year_id, name, details FROM "group"');
        $this->addSql('DROP TABLE "group"');
        $this->addSql('ALTER TABLE "group_new" RENAME TO "group"');

        $this->addSql('CREATE INDEX IDX_group_py    ON "group"     (programme_year_id)');
        $this->addSql('CREATE INDEX IDX_gtu_group   ON group_tutor (group_id)');
        $this->addSql('CREATE INDEX IDX_gtu_teacher ON group_tutor (teacher_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        // Recrear group con tutor_id
        $this->addSql(<<<'SQL'
            CREATE TABLE "group_old" (
                id                CHAR(36)     NOT NULL,
                programme_year_id CHAR(36)     NOT NULL,
                tutor_id          CHAR(36)     DEFAULT NULL,
                name              VARCHAR(255) NOT NULL,
                details           TEXT         DEFAULT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_group_py    FOREIGN KEY (programme_year_id) REFERENCES programme_year(id),
                CONSTRAINT FK_group_tutor FOREIGN KEY (tutor_id)          REFERENCES teacher(id)
            )
        SQL);
        $this->addSql('INSERT INTO "group_old" (id, programme_year_id, name, details) SELECT id, programme_year_id, name, details FROM "group"');
        $this->addSql('UPDATE "group_old" SET tutor_id = (SELECT teacher_id FROM group_tutor WHERE group_id = "group_old".id LIMIT 1)');
        $this->addSql('DROP TABLE "group"');
        $this->addSql('ALTER TABLE "group_old" RENAME TO "group"');

        $this->addSql('CREATE INDEX IDX_group_py    ON "group" (programme_year_id)');
        $this->addSql('CREATE INDEX IDX_group_tutor ON "group" (tutor_id)');

        $this->addSql('DROP TABLE group_tutor');
    }
}
