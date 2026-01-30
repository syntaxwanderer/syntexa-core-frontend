<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

class LayoutLoader
{
    /**
     * Locate an active (project) layout template for the given handle.
     *
     * @return array{template:string,module:string,path:string}|null
     */
    public static function loadHandle(string $handle): ?array
    {
        $projectLayout = self::findProjectLayout($handle);
        if ($projectLayout !== null) {
            return $projectLayout;
        }

        error_log("Layout '{$handle}' is not activated. Run 'bin/syntexa layout:generate {$handle}' to copy it into src/.");
        return null;
    }

    private static function findProjectLayout(string $handle): ?array
    {
        $root = self::getProjectRoot() . '/src/modules';
        $pattern = $root . '/*/Layout/' . $handle . '.html.twig';
        $matches = glob($pattern, GLOB_NOSORT) ?: [];
        if (empty($matches)) {
            return null;
        }

        sort($matches);

        $path = $matches[0];
        $module = basename(dirname(dirname($path)));
        $alias = self::aliasForModule($module);
        $template = "@{$alias}/" . basename($path);

        return [
            'template' => $template,
            'module' => $module,
            'path' => $path,
        ];
    }

    private static function aliasForModule(string $module): string
    {
        return 'project-layouts-' . $module;
    }

    private static function getProjectRoot(): string
    {
        static $root = null;
        if ($root !== null) {
            return $root;
        }

        // Calculate project root: from packages/syntexa/core-frontend/src/Layout go up
        // In container, file is at /var/www/packages/syntexa/core-frontend/src/Layout/LayoutLoader.php
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
}