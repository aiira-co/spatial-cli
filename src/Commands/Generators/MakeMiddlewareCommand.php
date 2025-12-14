<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

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

        if (empty($args['_positional'])) {
            $this->error("Please provide a middleware name.");
            $this->output("Usage: php spatial make:middleware <name> [--folder=<Folder>]");
            $this->output("Example: php spatial make:middleware RateLimit");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $folder = $args['folder'] ?? 'Middlewares';
        $folder = $this->toPascalCase($folder);

        $content = $this->generateMiddleware($name, $folder);
        
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

    private function generateMiddleware(string $name, string $folder): string
    {
        $shortName = str_replace('Middleware', '', $name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Infrastructure\\{$folder};

use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Http\\Message\\ServerRequestInterface;
use Psr\\Http\\Server\\MiddlewareInterface;
use Psr\\Http\\Server\\RequestHandlerInterface;
use GuzzleHttp\\Psr7\\Response;

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
        // Inject dependencies here
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
        // Before request processing
        // Example: Check authorization, rate limits, etc.
        
        // Continue to next middleware/handler
        \$response = \$handler->handle(\$request);
        
        // After request processing
        // Example: Add headers, log response, etc.
        
        return \$response;
    }

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

