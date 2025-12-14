<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Seeder Command
 * 
 * Generates a new database seeder class.
 * 
 * @example php spatial make:seeder UsersSeeder
 * @example php spatial make:seeder ProductsSeeder --connection=default
 * 
 * @package Spatial\Console\Commands
 */
class MakeSeederCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:seeder';
    }

    public function getDescription(): string
    {
        return 'Create a new database seeder';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a seeder name.");
            $this->output("Usage: php spatial make:seeder <name> [--connection=<connection>]");
            $this->output("Example: php spatial make:seeder UsersSeeder");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $connection = $args['connection'] ?? 'default';

        $content = $this->generateSeeder($name, $connection);
        
        $path = $this->getBasePath() . "/src/core/Database/Seeders/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Seeder already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created seeder: {$path}");
            $this->output("");
            $this->output("Run seeder with:");
            $this->output("  php spatial db:seed --class={$name}");
            return 0;
        }

        $this->error("Failed to create seeder");
        return 1;
    }

    private function generateSeeder(string $name, string $connection): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Database\\Seeders;

use Doctrine\\ORM\\EntityManagerInterface;
use Spatial\\Database\\Seeder;

/**
 * {$name}
 * 
 * Database seeder for test/development data.
 * 
 * @package Core\\Database\\Seeders
 */
class {$name} extends Seeder
{
    protected string \$connection = '{$connection}';

    /**
     * Run the seeder.
     *
     * @param EntityManagerInterface \$em
     * @return void
     */
    public function run(EntityManagerInterface \$em): void
    {
        // Example: Create test data
        // for (\$i = 0; \$i < 10; \$i++) {
        //     \$entity = new YourEntity();
        //     \$entity->setName('Test ' . \$i);
        //     \$em->persist(\$entity);
        // }
        //
        // \$em->flush();

        \$this->info("Seeding {$name}...");
        
        // TODO: Implement seeding logic
        
        \$this->success("{$name} completed.");
    }
}
PHP;
    }
}

