<?php

declare(strict_types=1);

namespace Spatial\Cli\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads and manages .spatial.yml configuration
 * 
 * Provides cached access to project configuration with support for:
 * - Global generator defaults
 * - Command-specific overrides
 * - Project settings
 * 
 * @package Spatial\Cli\Config
 */
class ConfigLoader
{
    private static ?array $config = null;
    private static bool $attempted = false;

    /**
     * Load configuration from .spatial.yml
     * 
     * @param string $basePath Project root directory
     * @return array Parsed configuration array
     */
    public static function load(string $basePath): array
    {
        if (self::$attempted) {
            return self::$config ?? [];
        }

        self::$attempted = true;
        $configPath = $basePath . '/.spatial.yml';

        if (!file_exists($configPath)) {
            self::$config = [];
            return [];
        }

        try {
            $content = file_get_contents($configPath);
            if ($content === false) {
                self::$config = [];
                return [];
            }
            
            self::$config = Yaml::parse($content) ?? [];
            return self::$config;
        } catch (\Exception $e) {
            // Invalid YAML - return empty config and continue
            error_log("Warning: Invalid .spatial.yml file: " . $e->getMessage());
            self::$config = [];
            return [];
        }
    }

    /**
     * Get generator defaults for a specific command
     * 
     * Merges global defaults with command-specific overrides.
     * Command overrides take precedence over global defaults.
     * 
     * @param string $basePath Project root directory
     * @param string $commandName Command name (e.g., 'make:query')
     * @return array Generator configuration
     */
    public static function getGeneratorDefaults(string $basePath, string $commandName): array
    {
        $config = self::load($basePath);
        
        // Start with global defaults
        $defaults = $config['generators']['defaults'] ?? [];
        
        // Apply command-specific overrides
        $overrides = $config['generators']['overrides'][$commandName] ?? [];
        
        return array_merge($defaults, $overrides);
    }

    /**
     * Get project configuration
     * 
     * @param string $basePath Project root directory
     * @return array Project settings (namespace, paths, etc.)
     */
    public static function getProjectConfig(string $basePath): array
    {
        $config = self::load($basePath);
        return $config['project'] ?? [];
    }

    /**
     * Check if config file exists
     * 
     * @param string $basePath Project root directory
     * @return bool True if .spatial.yml exists
     */
    public static function exists(string $basePath): bool
    {
        return file_exists($basePath . '/.spatial.yml');
    }

    /**
     * Reset cached config (useful for testing)
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$config = null;
        self::$attempted = false;
    }
}
