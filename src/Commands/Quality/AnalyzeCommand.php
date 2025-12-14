<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Quality;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Analyze Command
 * 
 * Runs static analysis using PHPStan.
 * 
 * @example php spatial analyze
 * @example php spatial analyze --level=5
 * 
 * @package Spatial\Console\Commands
 */
class AnalyzeCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'analyze';
    }

    public function getDescription(): string
    {
        return 'Run static analysis (PHPStan)';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $level = $args['level'] ?? '0';
        $path = $args['path'] ?? 'src';

        $this->output("Running static analysis...");
        $this->output("");

        // Check if phpstan is available
        $phpstanPath = $this->findExecutable('phpstan');

        if ($phpstanPath === null) {
            $this->error("PHPStan not found.");
            $this->output("Install with: composer require --dev phpstan/phpstan");
            return 1;
        }

        $command = "\"{$phpstanPath}\" analyse {$path} --level={$level}";
        
        $this->output("Command: {$command}");
        $this->output("");

        passthru($command, $exitCode);

        if ($exitCode === 0) {
            $this->success("No issues found at level {$level}!");
        } else {
            $this->warning("Analysis found issues. Fix them or lower the --level.");
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
}

