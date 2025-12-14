<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Listener Command
 * 
 * Generates a new event listener class.
 * 
 * @example php spatial make:listener SendConfirmationEmail --event=OrderCreatedEvent
 * @example php spatial make:listener NotifyAdmin --event=UserRegisteredEvent
 * 
 * @package Spatial\Console\Commands
 */
class MakeListenerCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:listener';
    }

    public function getDescription(): string
    {
        return 'Create a new event listener';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a listener name.");
            $this->output("Usage: php spatial make:listener <name> --event=<EventClass>");
            $this->output("Example: php spatial make:listener SendConfirmationEmail --event=OrderCreatedEvent");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Listener')) {
            $name .= 'Listener';
        }

        $event = $args['event'] ?? null;
        if ($event === null) {
            $this->error("--event parameter is required.");
            return 1;
        }

        $event = $this->toPascalCase($event);

        $content = $this->generateListener($name, $event);
        
        $path = $this->getBasePath() . "/src/core/Application/Listeners/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Listener already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created listener: {$path}");
            $this->output("");
            $this->output("Register this listener in your event configuration.");
            return 0;
        }

        $this->error("Failed to create listener");
        return 1;
    }

    private function generateListener(string $name, string $event): string
    {
        $shortName = str_replace('Listener', '', $name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Listeners;

use OpenTelemetry\\API\\Trace\\TracerInterface;
use Psr\\Log\\LoggerInterface;
use Spatial\\Events\\Attributes\\Listener;

/**
 * {$name}
 * 
 * Handles {$event} events.
 * 
 * @package Core\\Application\\Listeners
 */
#[Listener({$event}::class)]
class {$name}
{
    public function __construct(
        protected LoggerInterface \$logger,
        protected TracerInterface \$tracer
    ) {}

    /**
     * Handle the event.
     *
     * @param {$event} \$event
     * @return void
     */
    public function handle({$event} \$event): void
    {
        \$span = \$this->tracer->spanBuilder('{$name}::handle')->startSpan();
        \$scope = \$span->activate();

        try {
            \$this->logger->info('{$shortName} started', [
                'event' => \$event::eventName(),
                'correlationId' => \$event->correlationId
            ]);

            // TODO: Implement listener logic
            // Example: Send email, notify external service, update cache, etc.

            \$this->logger->info('{$shortName} completed');

        } catch (\\Exception \$e) {
            \$this->logger->error('{$shortName} failed', [
                'exception' => \$e->getMessage()
            ]);
            \$span->recordException(\$e);
            throw \$e;
        } finally {
            \$scope->detach();
            \$span->end();
        }
    }
}
PHP;
    }
}

