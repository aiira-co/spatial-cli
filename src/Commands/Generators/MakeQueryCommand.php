<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Query Command
 * 
 * Generates a new CQRS Query and Handler following spatial's structure.
 * Includes OpenTelemetry tracing and logging patterns.
 * 
 * @example php spatial make:query GetUsers --module=Identity --entity=User
 * @example php spatial make:query GetProducts --module=App --entity=Product
 * 
 * @package Spatial\Console\Commands
 */
class MakeQueryCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:query';
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS query and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a query name.");
            $this->output("Usage: php spatial make:query <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:query GetUsers --module=Identity --entity=User");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);

        // Module and entity are required
        $module = $args['module'] ?? null;
        $entity = $args['entity'] ?? null;

        if ($module === null || $entity === null) {
            $this->error("Both --module and --entity parameters are required.");
            $this->output("Usage: php spatial make:query <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:query GetProducts --module=App --entity=Product");
            return 1;
        }

        $module = $this->toPascalCase($module);
        $entity = $this->toPascalCase($entity);

        $queryContent = $this->generateQuery($name, $module, $entity);
        $handlerContent = $this->generateHandler($name, $module, $entity);
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Queries";
        
        $queryPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        if (file_exists($queryPath)) {
            $this->error("Query already exists: {$queryPath}");
            return 1;
        }

        $this->ensureDirectory($basePath);

        $this->writeFile($queryPath, $queryContent);
        $this->success("Created query: {$queryPath}");
        
        $this->writeFile($handlerPath, $handlerContent);
        $this->success("Created handler: {$handlerPath}");

        return 0;
    }

    private function generateQuery(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Queries;

use Spatial\\Psr7\\Request;
use Spatial\\Router\\ActivatedRoute;

/**
 * {$name} Query
 * 
 * Request to fetch data, passed to its Handler.
 */
class {$name} extends Request
{
    public ?ActivatedRoute \$params = null;
    public ?object \$appClaimParam = null;
    public ?int \$id = null;

    /**
     * Execute the query logic.
     * Called by the handler.
     * 
     * @return mixed
     */
    public function fetchData(): mixed
    {
        // Get entity managers if needed
        // \$this->getEntityManager();
        
        // Access query params via \$this->params
        // Pagination: \$this->params->page, \$this->params->pageSize
        // Search: \$this->params->searchValue, \$this->params->searchFields
        
        // TODO: Implement query logic
        
        \$page = \$this->params?->page ?? 1;
        \$pageSize = \$this->params?->pageSize ?? 10;
        
        return [
            'items' => [],
            'pagination' => [
                'currentPage' => \$page,
                'pageSize' => \$pageSize,
                'totalPages' => 0,
                'totalItems' => 0
            ]
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

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Queries;

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
 * Handles the {$name} query with OpenTelemetry tracing and logging.
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
     * Handle the query request.
     *
     * @param {$name}|ServerRequestInterface \$request
     * @return ResponseInterface
     */
    public function handle({$name}|ServerRequestInterface \$request): ResponseInterface
    {
        // Start tracing span
        \$span = \$this->tracer->spanBuilder('{$name}Handler::handle')->startSpan();
        \$scope = \$span->activate();

        \$this->logger->info('Starting {$name} query.');

        \$res = new ServerResponse();

        try {
            // Execute query logic
            \$result = \$request->fetchData();

            \$res->data = \$result['items'] ?? \$result;
            \$res->message = 'Data fetched successfully';

            // Set pagination if available
            if (isset(\$result['pagination'])) {
                \$res->paginate(
                    currentPage: \$result['pagination']['currentPage'],
                    pageSize: \$result['pagination']['pageSize'],
                    totalPages: \$result['pagination']['totalPages'],
                    totalItems: \$result['pagination']['totalItems']
                );
            }

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

