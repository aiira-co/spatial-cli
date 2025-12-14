<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Service Command
 * 
 * Generates a new service in the Infrastructure layer.
 * 
 * @example php spatial make:service Email
 * @example php spatial make:service Payment --folder=Gateway
 * 
 * @package Spatial\Console\Commands
 */
class MakeServiceCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:service';
    }

    public function getDescription(): string
    {
        return 'Create a new infrastructure service';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a service name.");
            $this->output("Usage: php spatial make:service <name> [--folder=<Folder>]");
            $this->output("Example: php spatial make:service Email");
            $this->output("Example: php spatial make:service PaymentGateway --folder=Gateway");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Service')) {
            $name .= 'Service';
        }

        $folder = $args['folder'] ?? 'Services';
        $folder = $this->toPascalCase($folder);

        $content = $this->generateService($name, $folder);
        
        $path = $this->getBasePath() . "/src/infrastructure/{$folder}/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Service already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created service: {$path}");
            $this->output("");
            $this->output("Don't forget to register this service as a provider in your module!");
            return 0;
        }

        $this->error("Failed to create service");
        return 1;
    }

    private function generateService(string $name, string $folder): string
    {
        $shortName = str_replace('Service', '', $name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Infrastructure\\{$folder};

/**
 * {$name}
 * 
 * Infrastructure service for {$shortName} operations.
 * 
 * @package Infrastructure\\{$folder}
 */
class {$name}
{
    public function __construct(
        // Inject dependencies here
    ) {}

    /**
     * Example method - customize as needed.
     * 
     * @param mixed \$data
     * @return bool
     */
    public function process(mixed \$data): bool
    {
        // TODO: Implement service logic
        
        return true;
    }

    /**
     * Example async method.
     */
    public function send(string \$to, string \$subject, string \$body): bool
    {
        // TODO: Implement send logic
        
        return true;
    }
}
PHP;
    }
}

