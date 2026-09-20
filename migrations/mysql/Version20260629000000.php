<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * No hay nada que hacer en MySQL/MariaDB: la collation utf8mb4_unicode_ci ya
 * usada por sus migraciones (ver p. ej. Version20260101000000) compara texto
 * ignorando tildes por defecto ("José" = "Jose"), así que las búsquedas por
 * texto ya son insensibles a tildes sin ningún cambio adicional. Este
 * fichero solo mantiene alineados los números de versión entre las tres
 * plataformas — ver Version20260629000000 en migrations/postgresql/.
 */
final class Version20260629000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sin cambios: MySQL/MariaDB ya es insensible a tildes en las búsquedas por texto con su collation actual';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Esta migración sólo puede ejecutarse en MySQL o MariaDB.'
        );
    }
}
