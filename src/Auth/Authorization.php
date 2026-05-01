<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class Authorization
{
    public static function requireAdminPermission(array $user, string $permission): void
    {
        $isAdmin = (bool) ($user['is_admin'] ?? false);
        $permissions = $user['permissions'] ?? [];

        if (!$isAdmin || !in_array($permission, $permissions, true)) {
            throw new RuntimeException('Forbidden: missing permission ' . $permission);
        }
    }
}
