<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

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
class MakeListenerCommand extends AbstractGenerator
{
    protected function getCommandName(): string
    {
        return 'make:listener';
    }

    public function getName(): string
    {
        return $this->getCommandName();
    }

    public function getDescription(): string
    {
        return 'Create a new event listener';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a listener name.");
            $this->output("Usage: php spatial make:listener <name> --event=<EventClass> [--logging] [--tracing]");
            $this->output("Example: php spatial make:listener SendConfirmationEmail --event=OrderCreatedEvent --logging");
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

        // Parse flags with config fallback
        $flags = $this->parseFlags($args, ['logging', 'tracing']);

        $content = $this->generateListener($name, $event, $flags['logging'], $flags['tracing']);
        
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

    private function generateListener(
        string $name, 
        string $event,
        bool $logging = false,
        bool $tracing = false
    ): string {
        $shortName = str_replace('Listener', '', $name);

        $imports = [
            'Spatial\\Events\\Attributes\\Listener',
        ];

        if ($tracing) {
            $imports[] = 'OpenTelemetry\\API\\Trace\\TracerInterface';
        }

        if ($logging) {
            $imports[] = 'Psr\\Log\\LoggerInterface';
        }

        sort($imports);
        $importsString = implode("\nuse ", $imports);

        $constructorParams = [];
        if ($logging) {
            $constructorParams[] = 'protected LoggerInterface $logger';
        }
        if ($tracing) {
            $constructorParams[] = 'protected TracerInterface $tracer';
        }

        $constructor = '';
        if (!empty($constructorParams)) {
            $params = implode(",\n        ", $constructorParams);
            $constructor = <<<PHP
    public function __construct(
        {$params}
    ) {}
PHP;
        }

        $tracingStart = '';
        $tracingEnd = '';
        $tracingError = '';

        if ($tracing) {
            $tracingStart = <<<PHP
        \$span = \$this->tracer->spanBuilder('{$name}::handle')->startSpan();
        \$scope = \$span->activate();
PHP;
            $tracingEnd = <<<PHP
            \$scope->detach();
            \$span->end();
PHP;
            $tracingError = "\$span->recordException(\$e);";
        }

        $loggingStart = $logging ? "\$this->logger->info('{$shortName} started', [\n                'event' => \$event::eventName(),\n                'correlationId' => \$event->correlationId\n            ]);" : '';
        $loggingEnd = $logging ? "\$this->logger->info('{$shortName} completed');" : '';
        $loggingError = $logging ? "\$this->logger->error('{$shortName} failed', [\n                'exception' => \$e->getMessage()\n            ]);" : '';

        $tryCatch = '';
        if ($logging || $tracing) {
            $tryCatch = <<<PHP
        try {
            {$loggingStart}

            // TODO: Implement listener logic
            // Example: Send email, notify external service, update cache, etc.

            {$loggingEnd}

        } catch (\\Exception \$e) {
            {$loggingError}
            {$tracingError}
            throw \$e;
        } finally {
            {$tracingEnd}
        }
PHP;
        } else {
             $tryCatch = <<<PHP
        // TODO: Implement listener logic
        // Example: Send email, notify external service, update cache, etc.
PHP;
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Listeners;

use {$importsString};

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
{$constructor}

    /**
     * Handle the event.
     *
     * @param {$event} \$event
     * @return void
     */
    public function handle({$event} \$event): void
    {
{$tracingStart}
{$tryCatch}
    }
}
PHP;
    }
}

