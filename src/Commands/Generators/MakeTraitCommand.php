<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Trait Command
 * 
 * Generates a new trait for domain-specific functionality like DB access.
 * 
 * @example php spatial make:trait Social --type=db
 * @example php spatial make:trait Identity --type=db
 * 
 * @package Spatial\Console\Commands
 */
class MakeTraitCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:trait';
    }

    public function getDescription(): string
    {
        return 'Create a new domain trait (e.g., for DB access)';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a trait name (domain name).");
            $this->output("Usage: php spatial make:trait <DomainName> [--type=db]");
            $this->output("Example: php spatial make:trait Social --type=db");
            return 1;
        }

        $domain = $this->toPascalCase($args['_positional'][0]);
        $type = $args['type'] ?? 'db';

        $content = match ($type) {
            'db' => $this->generateDbTrait($domain),
            default => $this->generateGenericTrait($domain)
        };

        $traitName = "{$domain}Trait";
        $path = $this->getBasePath() . "/src/core/Application/Traits/{$traitName}.php";
        
        if (file_exists($path)) {
            $this->error("Trait already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created trait: {$path}");
            $this->output("");
            $this->output("Usage in your Command/Query:");
            $this->output("  use Core\\Application\\Traits\\{$traitName};");
            $this->output("  class MyCommand extends Request {");
            $this->output("      use {$traitName};");
            $this->output("  }");
            return 0;
        }

        $this->error("Failed to create trait");
        return 1;
    }

    private function generateDbTrait(string $domain): string
    {
        $traitName = "{$domain}Trait";
        $emVar = "em" . $domain;
        $dbClass = "{$domain}DB";

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Traits;

use Doctrine\\ORM\\EntityManagerInterface;
use Infrastructure\\Resource\\{$dbClass};
use Spatial\\Core\\App;

/**
 * {$traitName}
 * 
 * Provides entity manager access for the {$domain} domain.
 * Use in Commands and Queries that need database access.
 * 
 * @package Core\\Application\\Traits
 */
trait {$traitName}
{
    protected ?EntityManagerInterface \${$emVar} = null;

    /**
     * Get the {$domain} entity manager.
     * Call this at the start of your command/query logic.
     */
    protected function getEntityManager(): void
    {
        if (\$this->{$emVar} === null) {
            /** @var {$dbClass} \$db */
            \$db = App::diContainer()->get({$dbClass}::class);
            \$this->{$emVar} = \$db->getEntityManager();
        }
    }

    /**
     * Release the entity manager.
     * Call this in the finally block of your handler.
     */
    public function releaseEntityManager(): void
    {
        if (\$this->{$emVar} !== null) {
            \$this->{$emVar}->clear();
            \$this->{$emVar} = null;
        }
    }

    /**
     * Persist and flush an entity.
     */
    protected function save(object \$entity): void
    {
        \$this->{$emVar}->persist(\$entity);
        \$this->{$emVar}->flush();
    }

    /**
     * Remove and flush an entity.
     */
    protected function remove(object \$entity): void
    {
        \$this->{$emVar}->remove(\$entity);
        \$this->{$emVar}->flush();
    }

    /**
     * Find entity by ID.
     */
    protected function findById(string \$entityClass, int \$id): ?object
    {
        return \$this->{$emVar}->find(\$entityClass, \$id);
    }

    /**
     * Get repository for entity class.
     */
    protected function getRepository(string \$entityClass)
    {
        return \$this->{$emVar}->getRepository(\$entityClass);
    }
}
PHP;
    }

    private function generateGenericTrait(string $domain): string
    {
        $traitName = "{$domain}Trait";

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Traits;

/**
 * {$traitName}
 * 
 * Shared functionality for the {$domain} domain.
 * 
 * @package Core\\Application\\Traits
 */
trait {$traitName}
{
    /**
     * Example helper method.
     * Add your domain-specific methods here.
     */
    protected function example{$domain}Method(): void
    {
        // TODO: Implement domain-specific logic
    }
}
PHP;
    }
}

