<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * SQLite se deja deliberadamente fuera de las búsquedas insensibles a
 * tildes: no tiene ninguna normalización Unicode nativa, y la única vía
 * (registrar una función personalizada en PHP en cada conexión, similar a
 * SqlitePragmasMiddleware) añade una complejidad que no compensa, ya que en
 * este proyecto SQLite es solo para desarrollo y tests — el despliegue real
 * (install-ubuntu.sh) siempre usa PostgreSQL. Este fichero solo mantiene
 * alineados los números de versión entre las tres plataformas — ver
 * Version20260629000000 en migrations/postgresql/.
 */
final class Version20260629000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sin cambios: las búsquedas insensibles a tildes no se implementan en SQLite (solo desarrollo/tests)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SqlitePlatform, 'Esta migración sólo puede ejecutarse en SQLite.');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SqlitePlatform, 'Esta migración sólo puede ejecutarse en SQLite.');
    }
}
