<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Database;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Migrate Create Command
 * 
 * Creates a new database migration file.
 * 
 * @example php spatial migrate:create AddOrdersTable --connection=default
 * @example php spatial migrate:create CreateUsersTable --connection=identity
 * 
 * @package Spatial\Console\Commands
 */
class MigrateCreateCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'migrate:create';
    }

    public function getDescription(): string
    {
        return 'Create a new database migration';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a migration name.");
            $this->output("Usage: php spatial migrate:create <name> [--connection=<connection>]");
            $this->output("Example: php spatial migrate:create CreateUsersTable --connection=identity");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        $connection = $args['connection'] ?? 'default';

        // Generate timestamp
        $timestamp = date('YmdHis');
        $filename = "{$timestamp}_{$name}.php";

        // Get migration directory based on connection
        $migrationsDir = $this->getMigrationsDir($connection);
        $this->ensureDirectory($migrationsDir);

        $filePath = "{$migrationsDir}/{$filename}";
        $content = $this->generateMigration($name, $timestamp, $connection);

        if ($this->writeFile($filePath, $content)) {
            $this->success("Created migration: {$filePath}");
            $this->output("");
            $this->output("Run migrations with:");
            $this->output("  php spatial migrate:run --connection={$connection}");
            return 0;
        }

        $this->error("Failed to create migration");
        return 1;
    }

    private function getMigrationsDir(string $connection): string
    {
        $schema = $this->toPascalCase($connection);
        return $this->getBasePath() . "/src/core/Domain/{$schema}/Migrations";
    }

    private function generateMigration(string $name, string $timestamp, string $connection): string
    {
        $schema = $this->toPascalCase($connection);
        $className = "Migration_{$timestamp}_{$name}";

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Domain\\{$schema}\\Migrations;

use Doctrine\\DBAL\\Schema\\Schema;
use Doctrine\\Migrations\\AbstractMigration;

/**
 * Migration: {$name}
 * Generated: {$timestamp}
 * Connection: {$connection}
 */
class {$className} extends AbstractMigration
{
    public function getDescription(): string
    {
        return '{$name}';
    }

    /**
     * Run the migration.
     */
    public function up(Schema \$schema): void
    {
        // Create table example:
        // \$table = \$schema->createTable('orders');
        // \$table->addColumn('id', 'integer', ['autoincrement' => true]);
        // \$table->addColumn('name', 'string', ['length' => 255]);
        // \$table->addColumn('created_at', 'datetime');
        // \$table->setPrimaryKey(['id']);
        
        // Or use raw SQL:
        // \$this->addSql('CREATE TABLE ...');
    }

    /**
     * Reverse the migration.
     */
    public function down(Schema \$schema): void
    {
        // Drop table example:
        // \$schema->dropTable('orders');
        
        // Or use raw SQL:
        // \$this->addSql('DROP TABLE ...');
    }
}
PHP;
    }
}

