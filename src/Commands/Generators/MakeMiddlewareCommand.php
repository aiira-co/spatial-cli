<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Cli\Config\ConfigLoader;
use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Middleware Command
 * 
 * Generates a new PSR-15 middleware.
 * 
 * @example php spatial make:middleware RateLimit
 * @example php spatial make:middleware Auth --folder=Identity
 * 
 * @package Spatial\Console\Commands
 */
class MakeMiddlewareCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Create a new PSR-15 middleware';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a middleware name.");
            $this->output("Usage: php spatial make:middleware <name> [--folder=<Folder>] [--logging] [--tracing]");
            $this->output("Example: php spatial make:middleware RateLimit --logging");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $folder = $args['folder'] ?? 'Middlewares';
        $folder = $this->toPascalCase($folder);

        // Load config defaults
        $configDefaults = ConfigLoader::getGeneratorDefaults($this->getBasePath(), 'make:middleware');

        // Parse optional flags (CLI args override config)
        $logging = isset($args['logging']) ? true : ($configDefaults['logging'] ?? false);
        $tracing = isset($args['tracing']) ? true : ($configDefaults['tracing'] ?? false);

        $content = $this->generateMiddleware($name, $folder, $logging, $tracing);
        
        $path = $this->getBasePath() . "/src/infrastructure/{$folder}/{$name}.php";
        
        if (file_exists($path)) {
            $this->error("Middleware already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created middleware: {$path}");
            $this->output("");
            $this->output("Register in your module providers to use it.");
            return 0;
        }

        $this->error("Failed to create middleware");
        return 1;
    }

    private function generateMiddleware(
        string $name, 
        string $folder,
        bool $logging = false,
        bool $tracing = false
    ): string {
        $shortName = str_replace('Middleware', '', $name);

        // Build imports
        $imports = [
            "Psr\\Http\\Message\\ResponseInterface",
            "Psr\\Http\\Message\\ServerRequestInterface",
            "Psr\\Http\\Server\\MiddlewareInterface",
            "Psr\\Http\\Server\\RequestHandlerInterface",
            "GuzzleHttp\\Psr7\\Response",
        ];

        if ($logging) {
            $imports[] = "Psr\\Log\\LoggerInterface";
        }

        if ($tracing) {
            $imports[] = "OpenTelemetry\\API\\Trace\\TracerInterface";
            $imports[] = "OpenTelemetry\\API\\Trace\\StatusCode";
        }

        $importsString = implode("\nuse ", $imports);

        // Constructor
        $constructorParams = [];
        if ($logging) {
            $constructorParams[] = 'protected ?LoggerInterface $logger = null';
        }
        if ($tracing) {
            $constructorParams[] = 'protected ?TracerInterface $tracer = null';
        }

        $constructorBody = empty($constructorParams) 
            ? "// Inject dependencies here" 
            : implode(",\n        ", $constructorParams);

        // Tracing
        $tracingStart = '';
        $tracingEnd = '';
        $loggingBefore = '';
        $loggingAfter = '';

        if ($tracing) {
            $tracingStart = <<<PHP
        // Start tracing span
        \$span = \$this->tracer?->spanBuilder('{$name}::process')->startSpan();
        \$scope = \$span?->activate();

PHP;
            $tracingEnd = <<<PHP
        } finally {
            \$span?->setStatus(StatusCode::STATUS_OK);
            \$scope?->detach();
            \$span?->end();
        }
PHP;
        }

        if ($logging) {
            $loggingBefore = "\$this->logger?->info('{$shortName} middleware: processing request');";
            $loggingAfter = "\$this->logger?->info('{$shortName} middleware: request processed');";
        }

        $tryBlock = ($tracing || $logging) ? "try {" : "";
        $catchFinally = $tracing ? <<<PHP

        } catch (\\Exception \$e) {
            \$this->logger?->error('{$shortName} middleware error', ['exception' => \$e->getMessage()]);
            \$span?->recordException(\$e);
            \$span?->setStatus(StatusCode::STATUS_ERROR, \$e->getMessage());
            throw \$e;
PHP : "";

        return <<<PHP
<?php

declare(strict_types=1);

namespace Infrastructure\\{$folder};

use {$importsString};

/**
 * {$name}
 * 
 * PSR-15 Middleware for {$shortName} handling.
 * 
 * @package Infrastructure\\{$folder}
 */
class {$name} implements MiddlewareInterface
{
    public function __construct(
        {$constructorBody}
    ) {}

    /**
     * Process the request through the middleware.
     *
     * @param ServerRequestInterface \$request
     * @param RequestHandlerInterface \$handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface \$request,
        RequestHandlerInterface \$handler
    ): ResponseInterface {
{$tracingStart}{$tryBlock}
        {$loggingBefore}
        // Before request processing
        // Example: Check authorization, rate limits, etc.
        
        // Continue to next middleware/handler
        \$response = \$handler->handle(\$request);
        
        // After request processing
        // Example: Add headers, log response, etc.
        {$loggingAfter}
        
        return \$response;{$catchFinally}
{$tracingEnd}

    /**
     * Return an error response.
     */
    protected function error(string \$message, int \$status = 400): ResponseInterface
    {
        \$response = new Response(\$status);
        \$response->getBody()->write(json_encode([
            'error' => true,
            'message' => \$message,
        ]));
        
        return \$response->withHeader('Content-Type', 'application/json');
    }
}
PHP;
    }
}

