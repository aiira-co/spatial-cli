<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Cli\Config\ConfigLoader;
use Spatial\Console\AbstractCommand;

/**
 * Base class for all code generators
 * 
 * Provides common functionality:
 * - Parameter validation and parsing
 * - Configuration loading and merging
 * - File creation with validation
 * - Flag parsing with config fallback
 * 
 * @package Spatial\Cli\Commands\Generators
 */
abstract class AbstractGenerator extends AbstractCommand
{
    protected bool $dryRun = false;

    /**
     * Get command name for config lookup
     * 
     * @return string Command name (e.g., 'make:query')
     */
    abstract protected function getCommandName(): string;

    /**
     * Check if running in dry-run mode
     * 
     * @param array $args Command arguments
     * @return bool True if dry-run flag is set
     */
    protected function isDryRun(array $args): bool
    {
        return isset($args['dry-run']) || isset($args['preview']);
    }

    /**
     * Show error with suggestions
     * 
     * @param string $message Error message
     * @param array $suggestions List of suggestions
     * @param string|null $command Correct usage example
     * @return void
     */
    protected function errorWithSuggestions(
        string $message, 
        array $suggestions = [],
        ?string $command = null
    ): void {
        $this->error($message);
        $this->output("");

        if (!empty($suggestions)) {
            $this->output("💡 Suggestions:");
            foreach ($suggestions as $suggestion) {
                $this->output("   • {$suggestion}");
            }
            $this->output("");
        }

        if ($command !== null) {
            $this->output("📖 Correct usage:");
            $this->output("   {$command}");
            $this->output("");
        }
    }

    /**
     * Suggest similar module names using Levenshtein distance
     * 
     * @param string $attempted Attempted module name
     * @return array Similar module names
     */
    protected function suggestModules(string $attempted): array
    {
        $availableModules = $this->listAvailableModules();
        $suggestions = [];

        foreach ($availableModules as $module) {
            $distance = levenshtein(strtolower($attempted), strtolower($module));
            if ($distance <= 3) { // Close match
                $suggestions[] = $module;
            }
        }

        return $suggestions;
    }

    /**
     * List available modules from presentation directory
     * 
     * @return array List of module names
     */
    protected function listAvailableModules(): array
    {
        $presentationPath = $this->getBasePath() . '/src/presentation';
        
        if (!is_dir($presentationPath)) {
            return [];
        }

        $modules = [];
        $items = scandir($presentationPath);
        
        foreach ($items as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            if (is_dir($presentationPath . '/' . $dir)) {
                $modules[] = $dir;
            }
        }

        return $modules;
    }

    /**
     * Parse and validate module/entity parameters with smart error messages
     * 
     * @param array $args Command arguments
     * @return array{module: string, entity: string}|null Parsed values or null on error
     */
    protected function parseModuleEntity(array $args): ?array
    {
        $module = $args['module'] ?? null;
        $entity = $args['entity'] ?? null;

        if ($module === null || $entity === null) {
            $suggestions = [];
            $command = "php spatial {$this->getCommandName()} <name> --module=<Module> --entity=<Entity>";

            if ($module === null) {
                $availableModules = $this->listAvailableModules();
                
                if (!empty($availableModules)) {
                    $suggestions[] = "Available modules: " . implode(', ', $availableModules);
                } else {
                    $suggestions[] = "No modules found. Create one with: php spatial make:module <ModuleName>";
                }
            }

            $this->errorWithSuggestions(
                "Both --module and --entity parameters are required.",
                $suggestions,
                $command
            );

            return null;
        }

        $module = $this->toPascalCase($module);
        $entity = $this->toPascalCase($entity);

        // Check if module exists
        $modulePath = $this->getBasePath() . "/src/presentation/{$module}";
        if (!is_dir($modulePath)) {
            $similarModules = $this->suggestModules($module);
            $suggestions = [];

            if (!empty($similarModules)) {
                $suggestions[] = "Did you mean: " . implode(', ', $similarModules) . "?";
            } else {
                $availableModules = $this->listAvailableModules();
                if (!empty($availableModules)) {
                    $suggestions[] = "Available modules: " . implode(', ', $availableModules);
                }
            }
            
            $suggestions[] = "Create the module first: php spatial make:module {$module}";

            $this->errorWithSuggestions(
                "Module '{$module}' not found.",
                $suggestions
            );

            return null;
        }

        return [
            'module' => $module,
            'entity' => $entity
        ];
    }

    /**
     * Parse flags with config file fallback
     * 
     * @param array $args Command arguments
     * @param array $flagNames Flag names to parse
     * @return array Parsed flag values (boolean flags)
     */
    protected function parseFlags(array $args, array $flagNames): array
    {
        $configDefaults = ConfigLoader::getGeneratorDefaults(
            $this->getBasePath(), 
            $this->getCommandName()
        );

        $result = [];
        foreach ($flagNames as $flagName) {
            // CLI args override config
            $result[$flagName] = isset($args[$flagName]) 
                ? true 
                : ($configDefaults[$flagName] ?? false);
        }

        return $result;
    }

    /**
     * Parse a specific flag value (non-boolean)
     * 
     * @param array $args Command arguments
     * @param string $flagName Flag name
     * @param mixed $default Default value if not set
     * @return mixed Flag value
     */
    protected function parseFlagValue(array $args, string $flagName, mixed $default): mixed
    {
        if (isset($args[$flagName])) {
            return $args[$flagName];
        }

        $configDefaults = ConfigLoader::getGeneratorDefaults(
            $this->getBasePath(),
            $this->getCommandName()
        );

        return $configDefaults[$flagName] ?? $default;
    }

    /**
     * Preview file content in dry-run mode
     * 
     * @param string $path File path
     * @param string $content File content
     * @param string $fileType Human-readable file type
     * @return void
     */
    protected function previewFile(string $path, string $content, string $fileType): void
    {
        $relativePath = str_replace($this->getBasePath() . '/', '', $path);
        $relativePath = str_replace($this->getBasePath() . '\\', '', $relativePath);
        $lineCount = substr_count($content, "\n") + 1;
        $size = strlen($content);

        $this->output("📄 Would create {$fileType}: {$relativePath}");
        $this->output("   Lines: {$lineCount} | Size: {$size} bytes");
        $this->output("");
        
        // Show first 20 lines as preview
        $lines = explode("\n", $content);
        $preview = array_slice($lines, 0, 20);
        
        $this->output("   Preview:");
        foreach ($preview as $i => $line) {
            $lineNum = str_pad((string)($i + 1), 3, ' ', STR_PAD_LEFT);
            $this->output("   {$lineNum} | {$line}");
        }
        
        if (count($lines) > 20) {
            $remaining = count($lines) - 20;
            $this->output("   ... ({$remaining} more lines)");
        }
        $this->output("");
    }

    /**
     * Create file with validation
     * 
     * @param string $path File path
     * @param string $content File content
     * @param string $fileType Human-readable file type (e.g., "query", "controller")
     * @return int Exit code (0 = success, 1 = error)
     */
    protected function createFile(string $path, string $content, string $fileType): int
    {
        if ($this->dryRun) {
            $this->previewFile($path, $content, $fileType);
            return 0;
        }

        if (file_exists($path)) {
            $this->error(ucfirst($fileType) . " already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if (!$this->writeFile($path, $content)) {
            $this->error("Failed to create {$fileType}");
            return 1;
        }

        $this->success("Created {$fileType}: {$path}");
        return 0;
    }

    /**
     * Create multiple files in a single operation
     * 
     * @param array $files Array of ['path' => string, 'content' => string, 'type' => string]
     * @return int Exit code (0 = all success, 1 = any error)
     */
    protected function createFiles(array $files): int
    {
        if ($this->dryRun) {
            $this->output("🔍 DRY RUN MODE - No files will be created");
            $this->output("");
        }

        foreach ($files as $file) {
            $result = $this->createFile($file['path'], $file['content'], $file['type']);
            if ($result !== 0 && !$this->dryRun) {
                return $result;
            }
        }

        if ($this->dryRun) {
            $this->output("✨ Dry run complete. Run without --dry-run to create files.");
        }

        return 0;
    }

    /**
     * Build dynamic imports string
     * 
     * @param array $baseImports Always-included imports
     * @param array $conditionalImports Map of condition => imports to add
     * @return string Imports string for use in templates
     */
    protected function buildImports(array $baseImports, array $conditionalImports = []): string
    {
        $imports = $baseImports;

        foreach ($conditionalImports as $condition => $additionalImports) {
            if ($condition) {
                $imports = array_merge($imports, (array)$additionalImports);
            }
        }

        sort($imports);
        return implode("\nuse ", $imports);
    }
}
