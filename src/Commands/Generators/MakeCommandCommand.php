<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Command Command
 * 
 * Generates a new CQRS Command and Handler following spatial's structure.
 * Includes OpenTelemetry tracing and logging patterns.
 * 
 * @example php spatial make:command CreateUser --module=Identity --entity=User
 * @example php spatial make:command UpdateProduct --module=App --entity=Product
 * 
 * @package Spatial\Console\Commands
 */
class MakeCommandCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:command';
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS command and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a command name.");
            $this->output("Usage: php spatial make:command <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:command CreateUser --module=Identity --entity=User");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);

        // Module and entity are required
        $module = $args['module'] ?? null;
        $entity = $args['entity'] ?? null;

        if ($module === null || $entity === null) {
            $this->error("Both --module and --entity parameters are required.");
            $this->output("Usage: php spatial make:command <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:command CreateProduct --module=App --entity=Product");
            return 1;
        }

        $module = $this->toPascalCase($module);
        $entity = $this->toPascalCase($entity);

        $commandContent = $this->generateCommand($name, $module, $entity);
        $handlerContent = $this->generateHandler($name, $module, $entity);
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Commands";
        
        $commandPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        if (file_exists($commandPath)) {
            $this->error("Command already exists: {$commandPath}");
            return 1;
        }

        $this->ensureDirectory($basePath);

        $this->writeFile($commandPath, $commandContent);
        $this->success("Created command: {$commandPath}");
        
        $this->writeFile($handlerPath, $handlerContent);
        $this->success("Created handler: {$handlerPath}");

        return 0;
    }

    private function generateCommand(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Commands;

use Spatial\\Psr7\\Request;

/**
 * {$name} Command
 * 
 * Request to be passed to its Handler.
 * Handler can use this class's properties and methods.
 */
class {$name} extends Request
{
    public object \$data;
    public ?object \$appClaimParam = null;

    /**
     * Execute the command logic.
     * Called by the handler.
     * 
     * @return mixed
     */
    public function execute(): mixed
    {
        // Get entity managers if needed
        // \$this->getEntityManager();
        
        // Access request data via \$this->data
        // Access client info via \$this->getAttribute('clientInfo')
        // Access claims via \$this->getAttribute('claims')
        
        // TODO: Implement command logic
        
        return [
            'success' => true,
            'message' => '{$name} executed successfully',
            'data' => null
        ];
    }
}
PHP;
    }

    private function generateHandler(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Commands;

use Common\\Response\\ServerResponse;
use Exception;
use GuzzleHttp\\Psr7\\Response;
use JsonException;
use OpenTelemetry\\API\\Trace\\StatusCode;
use OpenTelemetry\\API\\Trace\\TracerInterface;
use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Http\\Message\\ServerRequestInterface;
use Psr\\Log\\LoggerInterface;
use Spatial\\Psr7\\RequestHandler;

/**
 * {$name} Handler
 * 
 * Handles the {$name} command with OpenTelemetry tracing and logging.
 */
class {$name}Handler extends RequestHandler
{
    public function __construct(
        protected LoggerInterface \$logger,
        protected TracerInterface \$tracer
    ) {
        parent::__construct();
    }

    /**
     * Handle the command request.
     *
     * @param {$name}|ServerRequestInterface \$request
     * @return ResponseInterface
     */
    public function handle({$name}|ServerRequestInterface \$request): ResponseInterface
    {
        // Start tracing span
        \$span = \$this->tracer->spanBuilder('{$name}Handler::handle')->startSpan();
        \$scope = \$span->activate();

        \$this->logger->info('Starting {$name} process.');

        \$res = new ServerResponse();

        try {
            // Execute command logic
            \$result = \$request->execute();

            if (!(\$result['success'] ?? false)) {
                throw new Exception(\$result['message'] ?? 'Command execution failed');
            }

            \$res->data = \$result['data'] ?? null;
            \$res->message = \$result['message'] ?? '{$name} completed successfully';

            \$this->logger->info('{$name} completed successfully.');
            \$span->setStatus(StatusCode::STATUS_OK);

        } catch (JsonException \$e) {
            \$this->logger->error('JSON Exception in {$name}', ['exception' => \$e]);
            \$span->recordException(\$e)->setStatus(StatusCode::STATUS_ERROR, 'JsonException');
            \$res->logError(
                code: '500',
                detail: ['reason' => 'JSON encoding error', 'exception' => \$e->getMessage()]
            );
        } catch (Exception \$e) {
            \$this->logger->error('Exception in {$name}', ['exception' => \$e]);
            \$span->recordException(\$e)->setStatus(StatusCode::STATUS_ERROR, 'Exception');
            \$res->logError(
                code: '500',
                detail: ['reason' => \$e->getMessage()]
            );
        } finally {
            // Release resources
            \$request->releaseEntityManager();
            \$scope->detach();
            \$span->end();
        }

        \$response = new Response();
        \$response = \$response->withStatus(\$res->getResponseStatus());
        \$response->getBody()->write((string)\$res);

        return \$response;
    }
}
PHP;
    }
}

