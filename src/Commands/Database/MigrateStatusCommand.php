<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Database;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Migrate Status Command
 * 
 * Shows the status of all migrations.
 * 
 * @example php spatial migrate:status
 * @example php spatial migrate:status --connection=identity
 * 
 * @package Spatial\Console\Commands
 */
class MigrateStatusCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Show migration status';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $connection = $args['connection'] ?? null;

        // If no connection specified, show all
        if ($connection === null) {
            $connections = $this->getAvailableConnections();
        } else {
            $connections = [$connection];
        }

        foreach ($connections as $conn) {
            $this->showStatusForConnection($conn);
        }

        return 0;
    }

    private function getAvailableConnections(): array
    {
        $domainPath = $this->getBasePath() . '/src/core/Domain';
        if (!is_dir($domainPath)) {
            return ['default'];
        }

        $connections = [];
        foreach (scandir($domainPath) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (is_dir("{$domainPath}/{$dir}/Migrations")) {
                $connections[] = strtolower($dir);
            }
        }

        return $connections ?: ['default'];
    }

    private function showStatusForConnection(string $connection): void
    {
        $this->output("");
        $this->output("Connection: {$connection}");
        $this->output(str_repeat('-', 50));

        $migrationsDir = $this->getMigrationsDir($connection);
        $applied = $this->getAppliedMigrations($connection);

        if (!is_dir($migrationsDir)) {
            $this->output("  No migrations found.");
            return;
        }

        $files = glob("{$migrationsDir}/*.php");
        sort($files);

        if (empty($files)) {
            $this->output("  No migrations found.");
            return;
        }

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            preg_match('/^(\d+)_(.+)$/', $filename, $matches);
            
            if (empty($matches)) continue;

            $version = $matches[1];
            $name = $matches[2];

            $status = isset($applied[$version]) ? '✓ Applied' : '○ Pending';
            $date = $applied[$version]['applied_at'] ?? '';

            $this->output("  [{$status}] {$version} - {$name}" . ($date ? " ({$date})" : ''));
        }

        $pendingCount = count($files) - count($applied);
        $this->output("");
        $this->output("  Total: " . count($files) . " | Applied: " . count($applied) . " | Pending: {$pendingCount}");
    }

    private function getMigrationsDir(string $connection): string
    {
        $schema = $this->toPascalCase($connection);
        return $this->getBasePath() . "/src/core/Domain/{$schema}/Migrations";
    }

    private function getAppliedMigrations(string $connection): array
    {
        $trackingFile = $this->getBasePath() . "/var/migrations/{$connection}.json";
        
        if (file_exists($trackingFile)) {
            return json_decode(file_get_contents($trackingFile), true) ?? [];
        }

        return [];
    }
}

