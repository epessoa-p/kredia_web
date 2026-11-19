<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Menú lateral y módulo (color) de la página actual, a partir de config/menu.php.
 */
final class Menu
{
    /**
     * Secciones visibles para el usuario (filtradas por superadmin / permiso).
     *
     * @return array<int, array{title: string, items: array<int, array>}>
     */
    public static function sections(?User $user, ?Company $company): array
    {
        if (!$user) {
            return [];
        }

        $sections = [];
        foreach (config('menu.sections', []) as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                if (!Route::has($item['route'])) {
                    continue;
                }
                if (!empty($item['super_admin'])) {
                    if (!$user->is_super_admin) {
                        continue;
                    }
                } elseif (!self::allowed($user, $company, $item['permission'] ?? null)) {
                    continue;
                }

                $item['is_active'] = self::isActive($item);
                $items[] = $item;
            }

            if ($items) {
                $sections[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return $sections;
    }

    /** Clave de módulo de la ruta actual (para colorear la página). */
    public static function currentModule(): string
    {
        foreach (config('menu.sections', []) as $section) {
            foreach ($section['items'] as $item) {
                if (self::isActive($item)) {
                    return $item['module'];
                }
            }
        }

        foreach (config('menu.route_modules', []) as $pattern => $module) {
            if (request()->routeIs($pattern)) {
                return $module;
            }
        }

        return 'dashboard';
    }

    public static function color(string $module): string
    {
        return config("menu.modules.{$module}.color", '#2563eb');
    }

    private static function isActive(array $item): bool
    {
        $active = $item['active'] ?? [];
        if (!$active || !request()->routeIs(...$active)) {
            return false;
        }

        return empty($item['except']) || !request()->routeIs(...$item['except']);
    }

    private static function allowed(User $user, ?Company $company, string|array|null $permission): bool
    {
        if ($permission === null || $user->is_super_admin) {
            return true;
        }

        foreach ((array) $permission as $slug) {
            if ($user->hasPermissionInCompany($slug, $company)) {
                return true;
            }
        }

        return false;
    }
}
