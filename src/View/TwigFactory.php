<?php

declare(strict_types=1);

namespace Syntexa\Frontend\View;

use Syntexa\Core\ModuleRegistry;
use Syntexa\Core\Environment;
use Syntexa\Frontend\Layout\LayoutSlotRegistry;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class TwigFactory
{
    private static ?TwigEnvironment $twig = null;

    /**
     * Reset Twig instance (useful for testing or after module registration)
     */
    public static function reset(): void
    {
        self::$twig = null;
    }

    public static function get(): TwigEnvironment
    {
        if (self::$twig instanceof TwigEnvironment) {
            return self::$twig;
        }

        $loader = new FilesystemLoader();

        $modules = ModuleRegistry::getModules();
        $env = Environment::create();
        $activeTheme = $env->get('THEME', '');

        // Register themes first (override), then regular modules
        $themes = array_filter($modules, fn($m) => ($m['composerType'] ?? '') === 'syntexa-theme');
        $others = array_filter($modules, fn($m) => ($m['composerType'] ?? '') !== 'syntexa-theme');

        // If active theme specified, keep only matching themes (by alias or name)
        if ($activeTheme !== '') {
            $themes = array_filter($themes, function($m) use ($activeTheme) {
                $aliases = $m['aliases'] ?? [];
                return in_array($activeTheme, $aliases, true) || ($m['name'] ?? '') === $activeTheme;
            });
        }

        $ordered = array_merge($themes, $others);

        foreach ($ordered as $module) {
            $paths = $module['templatePaths'] ?? [];
            $aliases = $module['aliases'] ?? [$module['name']];
            foreach ($paths as $p) {
                if (!is_dir($p)) { continue; }
                foreach ($aliases as $alias) {
                    $loader->addPath($p, (string)$alias);
                }
            }
        }

        foreach (self::discoverProjectLayoutPaths() as $module => $path) {
            $loader->addPath($path, self::layoutAlias($module));
        }

        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        self::$twig = new TwigEnvironment($loader, [
            'cache' => $cacheDir,
            'auto_reload' => true,
            'strict_variables' => false,
        ]);

        self::registerFunctions();

        return self::$twig;
    }

    private static function getCacheDir(): string
    {
        $root = dirname(__DIR__, 5);
        return $root . '/var/cache/twig';
    }

    private static function discoverProjectLayoutPaths(): array
    {
        $projectRoot = self::getProjectRoot();
        $root = $projectRoot . '/src/modules';
        if (!is_dir($root)) {
            return [];
        }

        $paths = [];
        $modules = glob($root . '/*/Layout', GLOB_ONLYDIR) ?: [];
        foreach ($modules as $layoutDir) {
            $module = basename(dirname($layoutDir));
            $paths[$module] = $layoutDir;
        }

        return $paths;
    }

    private static function getProjectRoot(): string
    {
        static $root = null;
        if ($root !== null) {
            return $root;
        }

        // Calculate project root: from packages/syntexa/core-frontend/src/View go up
        // In container, file is at /var/www/packages/syntexa/core-frontend/src/View/TwigFactory.php
        // Going up 6 levels gives us /var/www, then we need to add /html
        $calculatedRoot = dirname(__FILE__, 6);
        // If we're in /var/www, add /html to get /var/www/html
        if ($calculatedRoot === '/var/www' || str_ends_with($calculatedRoot, '/www')) {
            $calculatedRoot = '/var/www/html';
        }
        
        // Verify it's actually a project root
        if (is_file($calculatedRoot . '/composer.json') && is_dir($calculatedRoot . '/src/modules')) {
            $root = $calculatedRoot;
            return $root;
        }

        // Fallback: try walking up from current directory
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (is_file($dir . '/composer.json') && is_dir($dir . '/src/modules')) {
                $root = $dir;
                return $root;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        // Last resort: use calculated root anyway
        $root = $calculatedRoot;
        return $root;
    }

    private static function layoutAlias(string $module): string
    {
        return 'project-layouts-' . $module;
    }

    private static function registerFunctions(): void
    {
        if (!(self::$twig instanceof TwigEnvironment)) {
            return;
        }

        if (class_exists(LayoutSlotRegistry::class)) {
            self::$twig->addFunction(new TwigFunction(
                'layout_slot',
                /**
                 * @param array<string, mixed> $context
                 */
                function (array $context, string $slot, array $extraContext = []): string {
                    $handle = $context['layout_handle'] ?? null;
                    if (!$handle) {
                        return '';
                    }

                    return LayoutSlotRegistry::render($handle, $slot, $context, $extraContext);
                },
                ['needs_context' => true, 'is_safe' => ['html']]
            ));
        }
    }
}


