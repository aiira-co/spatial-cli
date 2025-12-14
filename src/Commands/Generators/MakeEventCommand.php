<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Event Command
 * 
 * Generates a new domain event class.
 * 
 * @example php spatial make:event OrderCreated --module=Orders
 * @example php spatial make:event UserRegistered --module=Identity
 * 
 * @package Spatial\Console\Commands
 */
class MakeEventCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:event';
    }

    public function getDescription(): string
    {
        return 'Create a new domain event';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide an event name.");
            $this->output("Usage: php spatial make:event <name> --module=<Module>");
            $this->output("Example: php spatial make:event OrderCreated --module=Orders");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Event')) {
            $name .= 'Event';
        }

        $module = $args['module'] ?? 'App';
        $module = $this->toPascalCase($module);

        $content = $this->generateEvent($name, $module);
        
        $path = $this->getBasePath() . "/src/core/Application/Events/{$module}/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Event already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created event: {$path}");
            $this->output("");
            $this->output("Create a listener with:");
            $this->output("  php spatial make:listener Handle{$name} --event={$name}");
            return 0;
        }

        $this->error("Failed to create event");
        return 1;
    }

    private function generateEvent(string $name, string $module): string
    {
        $shortName = str_replace('Event', '', $name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Events\\{$module};

use DateTimeImmutable;

/**
 * {$name}
 * 
 * Domain event triggered when {$shortName} occurs.
 * 
 * @package Core\\Application\\Events\\{$module}
 */
class {$name}
{
    public readonly DateTimeImmutable \$occurredAt;

    public function __construct(
        public readonly mixed \$payload = null,
        public readonly ?string \$correlationId = null
    ) {
        \$this->occurredAt = new DateTimeImmutable();
    }

    /**
     * Get the event name.
     */
    public static function eventName(): string
    {
        return '{$module}.{$shortName}';
    }

    /**
     * Get event data for logging/serialization.
     */
    public function toArray(): array
    {
        return [
            'event' => self::eventName(),
            'payload' => \$this->payload,
            'correlationId' => \$this->correlationId,
            'occurredAt' => \$this->occurredAt->format('c')
        ];
    }
}
PHP;
    }
}

