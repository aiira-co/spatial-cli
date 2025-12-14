<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Database;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Database Seed Command
 * 
 * Runs database seeders to populate data.
 * 
 * @example php spatial db:seed
 * @example php spatial db:seed --class=UsersSeeder
 * 
 * @package Spatial\Console\Commands
 */
class DbSeedCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'db:seed';
    }

    public function getDescription(): string
    {
        return 'Run database seeders';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $class = $args['class'] ?? null;
        $seedersPath = $this->getBasePath() . '/src/core/Database/Seeders';

        if (!is_dir($seedersPath)) {
            $this->error("No seeders directory found.");
            $this->output("Create seeders with: php spatial make:seeder UsersSeeder");
            return 1;
        }

        $this->output("Running database seeders...");
        $this->output("");

        $seeders = $this->getSeeders($seedersPath, $class);

        if (empty($seeders)) {
            if ($class) {
                $this->error("Seeder not found: {$class}");
            } else {
                $this->output("No seeders found.");
            }
            return 1;
        }

        $count = 0;
        foreach ($seeders as $seederFile) {
            $result = $this->runSeeder($seederFile);
            if ($result) {
                $count++;
            }
        }

        $this->output("");
        $this->success("Ran {$count} seeder(s).");

        return 0;
    }

    private function getSeeders(string $path, ?string $class): array
    {
        $seeders = [];
        $files = glob("{$path}/*.php");

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            
            if ($class !== null && $filename !== $class) {
                continue;
            }

            $seeders[] = $file;
        }

        return $seeders;
    }

    private function runSeeder(string $file): bool
    {
        $filename = basename($file, '.php');
        $this->output("  Running: {$filename}");

        // Extract namespace and class
        $content = file_get_contents($file);
        
        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            $this->warning("    Could not determine namespace");
            return false;
        }

        $fqcn = $nsMatch[1] . '\\' . $filename;

        if (!class_exists($fqcn)) {
            require_once $file;
        }

        if (!class_exists($fqcn)) {
            $this->warning("    Could not load class: {$fqcn}");
            return false;
        }

        try {
            $seeder = new $fqcn();
            
            if (method_exists($seeder, 'setOutput')) {
                $seeder->setOutput($this->app);
            }

            // Get entity manager based on connection
            // This is a simplified version - in real usage, you'd get the EM from DI
            if (method_exists($seeder, 'run')) {
                // For now, pass null - the seeder should get its own EM
                $seeder->run(null);
            }

            $this->success("    ✓ Completed");
            return true;

        } catch (\Exception $e) {
            $this->error("    ✗ Failed: {$e->getMessage()}");
            return false;
        }
    }
}

