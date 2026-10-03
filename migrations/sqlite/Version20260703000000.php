<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Una estancia puede asociarse a varias enseñanzas: tabla stay_programme en lugar de stay.programme_id (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_programme (
                stay_id CHAR(36) NOT NULL,
                programme_id CHAR(36) NOT NULL,
                PRIMARY KEY(stay_id, programme_id),
                CONSTRAINT fk_sp_stay FOREIGN KEY (stay_id) REFERENCES stay(id) ON DELETE CASCADE,
                CONSTRAINT fk_sp_programme FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE RESTRICT
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_sp_stay ON stay_programme (stay_id)');
        $this->addSql('CREATE INDEX IDX_sp_programme ON stay_programme (programme_id)');
        $this->addSql('INSERT INTO stay_programme (stay_id, programme_id) SELECT id, programme_id FROM stay');

        // SQLite no puede borrar una columna con clave foránea: se reconstruye la tabla.
        $this->rebuildStay(withProgramme: false);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SqlitePlatform,
            'Esta migración sólo puede ejecutarse en SQLite.'
        );

        $this->rebuildStay(withProgramme: true);
        $this->addSql('DROP TABLE stay_programme');
    }

    private function rebuildStay(bool $withProgramme): void
    {
        $programmeColumn = $withProgramme ? "programme_id CHAR(36) NOT NULL,\n                " : '';
        $programmeFk     = $withProgramme ? ",\n                CONSTRAINT FK_stay_programme FOREIGN KEY (programme_id) REFERENCES programme(id)" : '';

        $this->addSql(<<<SQL
            CREATE TABLE stay_new (
                id CHAR(36) NOT NULL,
                academic_year_id CHAR(36) NOT NULL,
                {$programmeColumn}name VARCHAR(255) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                last_signature_reminder_sent_at DATETIME DEFAULT NULL,
                PRIMARY KEY(id),
                CONSTRAINT FK_stay_year FOREIGN KEY (academic_year_id) REFERENCES academic_year(id){$programmeFk}
            )
        SQL);

        if ($withProgramme) {
            $this->addSql(<<<'SQL'
                INSERT INTO stay_new (id, academic_year_id, programme_id, name, start_date, end_date, last_signature_reminder_sent_at)
                SELECT s.id, s.academic_year_id,
                    (SELECT sp.programme_id FROM stay_programme sp JOIN programme p ON p.id = sp.programme_id
                     WHERE sp.stay_id = s.id ORDER BY p.name, p.id LIMIT 1),
                    s.name, s.start_date, s.end_date, s.last_signature_reminder_sent_at
                FROM stay s
            SQL);
        } else {
            $this->addSql(<<<'SQL'
                INSERT INTO stay_new (id, academic_year_id, name, start_date, end_date, last_signature_reminder_sent_at)
                SELECT id, academic_year_id, name, start_date, end_date, last_signature_reminder_sent_at FROM stay
            SQL);
        }

        $this->addSql('DROP TABLE stay');
        $this->addSql('ALTER TABLE stay_new RENAME TO stay');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_uq_stay_name_year ON stay (name, academic_year_id)');
        $this->addSql('CREATE INDEX IDX_stay_year ON stay (academic_year_id)');
        if ($withProgramme) {
            $this->addSql('CREATE INDEX IDX_stay_programme ON stay (programme_id)');
        }
    }
}
