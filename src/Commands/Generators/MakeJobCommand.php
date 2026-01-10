<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Cli\Config\ConfigLoader;
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
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a job name.");
            $this->output("Usage: php spatial make:job <name> [--queue=<queue>] [--logging] [--tracing] [--retry=<tries>]");
            $this->output("Example: php spatial make:job SendEmailJob --logging --retry=5");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Job')) {
            $name .= 'Job';
        }

        $queue = $args['queue'] ?? 'default';

        // Load config defaults
        $configDefaults = ConfigLoader::getGeneratorDefaults($this->getBasePath(), 'make:job');

        // Parse optional flags (CLI args override config)
        $logging = isset($args['logging']) ? true : ($configDefaults['logging'] ?? false);
        $tracing = isset($args['tracing']) ? true : ($configDefaults['tracing'] ?? false);
        $retryCount = $args['retry'] ?? ($configDefaults['retry'] ?? 3);

        $content = $this->generateJob($name, $queue, $logging, $tracing, $retryCount);
        
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

    private function generateJob(
        string $name, 
        string $queue,
        bool $logging = false,
        bool $tracing = false,
        int $retryCount = 3
    ): string {
        $shortName = str_replace('Job', '', $name);

        // Build imports
        $imports = ["Spatial\\Queue\\Job"];

        if ($logging) {
            $imports[] = "Psr\\Log\\LoggerInterface";
        }

        if ($tracing) {
            $imports[] = "OpenTelemetry\\API\\Trace\\TracerInterface";
            $imports[] = "OpenTelemetry\\API\\Trace\\StatusCode";
        }

        $importsString = implode("\nuse ", $imports);

        // Handle method signature and body
        $handleParams = [];
        if ($logging) {
            $handleParams[] = 'LoggerInterface $logger';
        }
        if ($tracing) {
            $handleParams[] = 'TracerInterface $tracer';
        }

        if (empty($handleParams)) {
            $handleSignature = "public function handle(): void";
            $handleBody = <<<PHP
        // TODO: Implement job logic
        // Example: Send email, process file, call API, etc.
PHP;
        } else {
            $handleParamsStr = implode(", ", $handleParams);
            $handleSignature = "public function handle({$handleParamsStr}): void";
            
            $tracingStart = '';
            $tracingEnd = '';
            $loggingStart = '';
            $loggingEnd = '';
            $tryBlock = '';
            $catchBlock = '';

            if ($tracing) {
                $tracingStart = "\$span = \$tracer->spanBuilder('{$name}::handle')->startSpan();\n        \$scope = \$span->activate();\n\n        ";
                $tracingEnd = "\n\n            \$scope->detach();\n            \$span->end();";
                $tryBlock = "try {\n            ";
                $catchBlock = <<<PHP
        } catch (\\Exception \$e) {
PHP;
                if ($logging) {
                    $catchBlock .= "\n            \$logger->error('{$name} failed', ['exception' => \$e->getMessage()]);";
                }
                $catchBlock .= "\n            \$span->recordException(\$e);\n            throw \$e; // Re-throw to trigger retry\n        } finally {{$tracingEnd}\n        }";
            }

            if ($logging) {
                $loggingStart = "\$logger->info('{$name} started', ['payload' => \$this->payload]);\n\n            ";
                $loggingEnd = "\n\n            \$logger->info('{$name} completed');";
            }

            if (!$tracing && $logging) {
                $tryBlock = "try {\n            ";
                $catchBlock = <<<PHP
        } catch (\\Exception \$e) {
            \$logger->error('{$name} failed', ['exception' => \$e->getMessage()]);
            throw \$e; // Re-throw to trigger retry
        }
PHP;
            }

            $handleBody = "{$tracingStart}{$tryBlock}{$loggingStart}// TODO: Implement job logic\n            // Example: Send email, process file, call API, etc.{$loggingEnd}\n        {$catchBlock}";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Jobs;

use {$importsString};

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
    public int \$tries = {$retryCount};

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
     * @return void
     */
    {$handleSignature}
    {
        {$handleBody}
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

