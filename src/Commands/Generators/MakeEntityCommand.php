<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Entity Command
 * 
 * Generates a new Doctrine entity in the Domain layer.
 * 
 * @example php spatial make:entity User --schema=Identity
 * @example php spatial make:entity Product --schema=MyApp
 * 
 * @package Spatial\Console\Commands
 */
class MakeEntityCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:entity';
    }

    public function getDescription(): string
    {
        return 'Create a new Doctrine entity';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide an entity name.");
            $this->output("Usage: php spatial make:entity <name> --schema=<Schema>");
            $this->output("Example: php spatial make:entity User --schema=Identity");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        $schema = $args['schema'] ?? 'Default';
        $schema = $this->toPascalCase($schema);

        $content = $this->generateEntity($name, $schema);
        
        $path = $this->getBasePath() . "/src/core/Domain/{$schema}/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Entity already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created entity: {$path}");
            $this->output("");
            $this->output("Next steps:");
            $this->output("  1. Add properties to the entity");
            $this->output("  2. Run doctrine:schema:update to create tables");
            return 0;
        }

        $this->error("Failed to create entity");
        return 1;
    }

    private function generateEntity(string $name, string $schema): string
    {
        $tableName = $this->toSnakeCase($name) . 's';

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Domain\\{$schema};

use Doctrine\\ORM\\Mapping as ORM;
use DateTime;

/**
 * {$name} Entity
 * 
 * @package Core\\Domain\\{$schema}
 */
#[ORM\\Entity]
#[ORM\\Table(name: '{$tableName}')]
class {$name}
{
    #[ORM\\Id]
    #[ORM\\GeneratedValue]
    #[ORM\\Column(type: 'integer')]
    public ?int \$id = null;

    #[ORM\\Column(type: 'string', length: 255)]
    public string \$name;

    #[ORM\\Column(type: 'datetime')]
    public DateTime \$created;

    #[ORM\\Column(type: 'datetime', nullable: true)]
    public ?DateTime \$updated = null;

    #[ORM\\Column(type: 'boolean')]
    public bool \$active = true;

    // Add more properties as needed
    // Examples:
    //
    // #[ORM\\Column(type: 'string', length: 255, unique: true)]
    // public string \$email;
    //
    // #[ORM\\Column(type: 'text', nullable: true)]
    // public ?string \$description = null;
    //
    // #[ORM\\ManyToOne(targetEntity: Category::class)]
    // #[ORM\\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    // public ?Category \$category = null;
}
PHP;
    }

    /**
     * Convert to snake_case for table names.
     */
    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
    }
}

