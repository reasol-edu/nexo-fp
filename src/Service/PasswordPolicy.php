<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reglas mínimas que debe cumplir una contraseña al crearse o cambiarse.
 * Devuelve null si es válida, o una clave de traducción del dominio `messages`
 * si no lo es. Centralizar aquí permite que perfil y reset apliquen la misma
 * política sin duplicar reglas.
 *
 * Las reglas, de la más barata a la más cara: una longitud mínima; no contener el propio nombre de
 * usuario; y no figurar en filtraciones de datos conocidas (CompromisedPasswordChecker, que se omite si
 * no está conectado —como en los tests unitarios sencillos— o está desactivado).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /** Un nombre de usuario más corto que esto aparecería en una contraseña por pura casualidad. */
    private const MIN_USERNAME_LENGTH_TO_CHECK = 3;

    public function __construct(
        private readonly ?CompromisedPasswordChecker $breaches = null,
    ) {}

    public function firstViolationKey(string $password, ?string $username = null): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'profile.error.password_too_short';
        }

        if ($username !== null
            && mb_strlen($username) >= self::MIN_USERNAME_LENGTH_TO_CHECK
            && str_contains(mb_strtolower($password), mb_strtolower($username))
        ) {
            return 'profile.error.password_contains_username';
        }

        if ($this->breaches?->isCompromised($password) === true) {
            return 'profile.error.password_compromised';
        }

        return null;
    }
}
