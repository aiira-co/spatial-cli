<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Quality;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Lint Command
 * 
 * Runs code style checks using PHP_CodeSniffer.
 * 
 * @example php spatial lint
 * @example php spatial lint --fix
 * 
 * @package Spatial\Console\Commands
 */
class LintCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'lint';
    }

    public function getDescription(): string
    {
        return 'Check code style (PSR-12)';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $fix = isset($args['fix']);
        $path = $args['path'] ?? 'src';

        $this->output("Running code style check...");
        $this->output("");

        // Check if phpcs/phpcbf is available
        $phpcsPath = $this->findExecutable('phpcs');
        $phpcbfPath = $this->findExecutable('phpcbf');

        if ($fix) {
            if ($phpcbfPath === null) {
                $this->error("PHP Code Beautifier (phpcbf) not found.");
                $this->output("Install with: composer require --dev squizlabs/php_codesniffer");
                return 1;
            }

            $this->output("Fixing code style issues...");
            $command = "\"{$phpcbfPath}\" --standard=PSR12 {$path}";
        } else {
            if ($phpcsPath === null) {
                // Fallback to basic PHP syntax check
                return $this->runBasicLint($path);
            }

            $command = "\"{$phpcsPath}\" --standard=PSR12 {$path}";
        }

        $this->output("Command: {$command}");
        $this->output("");

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $this->success("No code style issues found!");
        } else {
            $this->warning("Code style issues found. Run 'php spatial lint --fix' to auto-fix.");
        }

        return $exitCode;
    }

    private function findExecutable(string $name): ?string
    {
        $paths = [
            $this->getBasePath() . "/vendor/bin/{$name}",
            $this->getBasePath() . "/vendor/bin/{$name}.bat",
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Check if globally available
        $result = shell_exec("where {$name} 2>&1") ?? shell_exec("which {$name} 2>&1");
        if ($result && !str_contains($result, 'not found') && !str_contains($result, 'Could not find')) {
            return trim($result);
        }

        return null;
    }

    private function runBasicLint(string $path): int
    {
        $this->output("Running basic PHP syntax check (install php_codesniffer for PSR-12)...");
        $this->output("");

        $errors = 0;
        $checked = 0;
        $fullPath = $this->getBasePath() . '/' . $path;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $checked++;
                $output = shell_exec("php -l \"{$file->getPathname()}\" 2>&1");
                
                if (!str_contains($output, 'No syntax errors')) {
                    $this->error($output);
                    $errors++;
                }
            }
        }

        $this->output("Checked {$checked} files.");

        if ($errors === 0) {
            $this->success("No syntax errors found!");
            return 0;
        }

        $this->error("Found {$errors} files with syntax errors.");
        return 1;
    }
}

