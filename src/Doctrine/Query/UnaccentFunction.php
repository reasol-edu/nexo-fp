<?php

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

use function sprintf;

/**
 * "UNACCENT" "(" StringPrimary ")"
 *
 * Envuelve una expresión con la función `unaccent` de PostgreSQL para que las
 * búsquedas por texto ignoren tildes/diéresis (p. ej. "Jose" encuentra
 * "José"). Se degrada de forma transparente (devuelve la expresión sin
 * envolver, idéntico a como quedaría hoy) en dos casos: en cualquier
 * plataforma que no sea PostgreSQL (MySQL/MariaDB ya son insensibles a
 * tildes con la collation utf8mb4_unicode_ci que usan sus migraciones;
 * SQLite se deja fuera de esta funcionalidad, ver migración
 * Version20260629000000), y en PostgreSQL cuando la extensión `unaccent` no
 * está instalada en la base de datos (por permisos insuficientes o porque
 * la instalación es anterior a que install-ubuntu.sh empezara a crearla).
 */
class UnaccentFunction extends FunctionNode
{
    /** Se resuelve una sola vez por proceso: no cambia mientras la app está en marcha. */
    private static ?bool $available = null;

    public Node $stringPrimary;

    public function getSql(SqlWalker $sqlWalker): string
    {
        $inner = $sqlWalker->walkSimpleArithmeticExpression($this->stringPrimary);

        $connection = $sqlWalker->getConnection();
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return $inner;
        }

        if (!self::isExtensionAvailable($connection)) {
            return $inner;
        }

        return sprintf('unaccent(%s)', $inner);
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->stringPrimary = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    private static function isExtensionAvailable(Connection $connection): bool
    {
        if (self::$available === null) {
            try {
                self::$available = (bool) $connection->fetchOne(
                    "SELECT 1 FROM pg_extension WHERE extname = 'unaccent'"
                );
            } catch (\Throwable) {
                self::$available = false;
            }
        }

        return self::$available;
    }
}
