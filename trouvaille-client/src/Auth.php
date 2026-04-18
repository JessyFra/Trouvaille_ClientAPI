<?php

class Auth
{
    public static function getToken(): ?string
    {
        return $_SESSION['jwt_token'] ?? null;
    }

    public static function setToken(string $token): void
    {
        $_SESSION['jwt_token'] = $token;
    }

    public static function getUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function setUser(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['jwt_token']);
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user']['role'] ?? '') === 'admin';
    }

    public static function isBanned(): bool
    {
        return ($_SESSION['user']['banned'] ?? false) === true;
    }

    public static function logout(): void
    {
        unset($_SESSION['jwt_token'], $_SESSION['user']);
    }
}
