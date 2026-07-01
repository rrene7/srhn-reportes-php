<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function hiddenInput(): string
    {
        return self::field();
    }

    public static function validateRequest(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        $requestToken = (string) ($_POST['_csrf'] ?? '');

        return is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $requestToken);
    }
}
