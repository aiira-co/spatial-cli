<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Job Command
 * 
 * Generates a new background job class.
 * 
 * @example php spatial make:job SendEmailJob
 * @example php spatial make:job ProcessOrderJob --queue=orders
 * 
 * @package Spatial\Console\Commands
 */
class MakeJobCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:job';
    }

    public function getDescription(): string
    {
        return 'Create a new background job';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a job name.");
            $this->output("Usage: php spatial make:job <name> [--queue=<queue>]");
            $this->output("Example: php spatial make:job SendEmailJob");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Job')) {
            $name .= 'Job';
        }

        $queue = $args['queue'] ?? 'default';

        $content = $this->generateJob($name, $queue);
        
        $path = $this->getBasePath() . "/src/core/Jobs/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Job already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created job: {$path}");
            $this->output("");
            $this->output("Dispatch job:");
            $this->output("  \$queue->dispatch(new {$name}(\$data));");
            $this->output("");
            $this->output("Process jobs:");
            $this->output("  php spatial queue:work --queue={$queue}");
            return 0;
        }

        $this->error("Failed to create job");
        return 1;
    }

    private function generateJob(string $name, string $queue): string
    {
        $shortName = str_replace('Job', '', $name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Jobs;

use OpenTelemetry\\API\\Trace\\TracerInterface;
use Psr\\Log\\LoggerInterface;
use Spatial\\Queue\\Job;

/**
 * {$name}
 * 
 * Background job for {$shortName} processing.
 * 
 * @package Core\\Jobs
 */
class {$name} extends Job
{
    /**
     * The queue name for this job.
     */
    public string \$queue = '{$queue}';

    /**
     * Number of retry attempts.
     */
    public int \$tries = 3;

    /**
     * Timeout in seconds.
     */
    public int \$timeout = 60;

    /**
     * Delay before processing (seconds).
     */
    public int \$delay = 0;

    public function __construct(
        public mixed \$payload = null
    ) {}

    /**
     * Execute the job.
     *
     * @param LoggerInterface \$logger
     * @param TracerInterface \$tracer
     * @return void
     */
    public function handle(LoggerInterface \$logger, TracerInterface \$tracer): void
    {
        \$span = \$tracer->spanBuilder('{$name}::handle')->startSpan();
        \$scope = \$span->activate();

        try {
            \$logger->info('{$name} started', ['payload' => \$this->payload]);

            // TODO: Implement job logic
            // Example: Send email, process file, call API, etc.

            \$logger->info('{$name} completed');

        } catch (\\Exception \$e) {
            \$logger->error('{$name} failed', ['exception' => \$e->getMessage()]);
            \$span->recordException(\$e);
            throw \$e; // Re-throw to trigger retry
        } finally {
            \$scope->detach();
            \$span->end();
        }
    }

    /**
     * Handle job failure after all retries.
     *
     * @param \\Exception \$exception
     * @return void
     */
    public function failed(\\Exception \$exception): void
    {
        // Log to failed jobs table, send alert, etc.
    }
}
PHP;
    }
}

