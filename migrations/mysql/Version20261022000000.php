<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'La coordinación dual y las estancias pasan de la enseñanza al nivel: programme_year_coordinator y stay_programme_year (MySQL / MariaDB)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        // Una enseñanza sin niveles no puede conservar coordinadores ni estancias (viven en el nivel): se avisa
        // antes de tocar nada, en lugar de perderlos en silencio.
        $orphans = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*) FROM programme p
            WHERE NOT EXISTS (SELECT 1 FROM programme_year py WHERE py.programme_id = p.id)
              AND (EXISTS (SELECT 1 FROM programme_coordinator pc WHERE pc.programme_id = p.id)
                   OR EXISTS (SELECT 1 FROM stay_programme sp WHERE sp.programme_id = p.id))
        SQL);
        $this->abortIf(
            $orphans > 0,
            sprintf('Hay %d enseñanza(s) sin niveles con coordinadores o estancias. Crea un nivel en cada una (Centro educativo → Oferta formativa) y vuelve a ejecutar la migración.', $orphans)
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE programme_year_coordinator (
                programme_year_id BINARY(16) NOT NULL,
                teacher_id        BINARY(16) NOT NULL,
                PRIMARY KEY (programme_year_id, teacher_id),
                INDEX IDX_3CD1BC209B62B32C (programme_year_id),
                INDEX IDX_3CD1BC2041807E1D (teacher_id),
                CONSTRAINT fk_pyc_programme_year FOREIGN KEY (programme_year_id) REFERENCES programme_year (id) ON DELETE CASCADE,
                CONSTRAINT fk_pyc_teacher        FOREIGN KEY (teacher_id)        REFERENCES teacher (id)        ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE stay_programme_year (
                stay_id           BINARY(16) NOT NULL,
                programme_year_id BINARY(16) NOT NULL,
                PRIMARY KEY (stay_id, programme_year_id),
                INDEX IDX_9C669306FB3AF7D6 (stay_id),
                INDEX IDX_9C6693069B62B32C (programme_year_id),
                CONSTRAINT fk_spy_stay           FOREIGN KEY (stay_id)           REFERENCES stay (id)           ON DELETE CASCADE,
                CONSTRAINT fk_spy_programme_year FOREIGN KEY (programme_year_id) REFERENCES programme_year (id) ON DELETE RESTRICT
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

        // Los datos existentes se copian a todos los niveles de cada enseñanza: nada cambia para quien ya tiene datos.
        $this->addSql('INSERT INTO programme_year_coordinator (programme_year_id, teacher_id) SELECT py.id, pc.teacher_id FROM programme_coordinator pc JOIN programme_year py ON py.programme_id = pc.programme_id');
        $this->addSql('INSERT INTO stay_programme_year (stay_id, programme_year_id) SELECT sp.stay_id, py.id FROM stay_programme sp JOIN programme_year py ON py.programme_id = sp.programme_id');

        $this->addSql('DROP TABLE programme_coordinator');
        $this->addSql('DROP TABLE stay_programme');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE programme_coordinator (
                programme_id BINARY(16) NOT NULL,
                teacher_id   BINARY(16) NOT NULL,
                PRIMARY KEY (programme_id, teacher_id),
                INDEX IDX_pc_programme (programme_id),
                INDEX IDX_pc_teacher (teacher_id),
                CONSTRAINT fk_pc_programme FOREIGN KEY (programme_id) REFERENCES programme (id) ON DELETE CASCADE,
                CONSTRAINT fk_pc_teacher   FOREIGN KEY (teacher_id)   REFERENCES teacher (id)   ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

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

        // Al volver atrás, la coordinación y las estancias vuelven a ser de la enseñanza: basta con un nivel.
        $this->addSql('INSERT INTO programme_coordinator (programme_id, teacher_id) SELECT DISTINCT py.programme_id, pyc.teacher_id FROM programme_year_coordinator pyc JOIN programme_year py ON py.id = pyc.programme_year_id');
        $this->addSql('INSERT INTO stay_programme (stay_id, programme_id) SELECT DISTINCT spy.stay_id, py.programme_id FROM stay_programme_year spy JOIN programme_year py ON py.id = spy.programme_year_id');

        $this->addSql('DROP TABLE programme_year_coordinator');
        $this->addSql('DROP TABLE stay_programme_year');
    }
}
