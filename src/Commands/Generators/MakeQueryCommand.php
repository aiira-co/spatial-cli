<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

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
class MakeQueryCommand extends AbstractGenerator
{
    protected function getCommandName(): string
    {
        return 'make:query';
    }

    public function getName(): string
    {
        return $this->getCommandName();
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS query and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a query name.");
            $this->output("Usage: php spatial make:query <name> --module=<Module> --entity=<Entity> [--logging] [--tracing] [--releaseEntity] [--dry-run]");
            $this->output("Example: php spatial make:query GetUsers --module=Identity --entity=User --logging --tracing");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);

        // Parse module and entity
        $parsed = $this->parseModuleEntity($args);
        if ($parsed === null) {
            return 1;
        }
        ['module' => $module, 'entity' => $entity] = $parsed;

        // Parse flags with config fallback
        $flags = $this->parseFlags($args, ['logging', 'tracing', 'releaseEntity']);

        $queryContent = $this->generateQuery($name, $module, $entity);
        $handlerContent = $this->generateHandler(
            $name, 
            $module, 
            $entity, 
            $flags['logging'], 
            $flags['tracing'], 
            $flags['releaseEntity']
        );
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Queries";
        
        $queryPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        // Create query and handler files
        return $this->createFiles([
            [
                'path' => $queryPath,
                'content' => $queryContent,
                'type' => 'query'
            ],
            [
                'path' => $handlerPath,
                'content' => $handlerContent,
                'type' => 'handler'
            ]
        ]);
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

    private function generateHandler(
        string $name, 
        string $module, 
        string $entity,
        bool $logging = false,
        bool $tracing = false,
        bool $releaseEntity = false
    ): string {
        $imports = [
            'Common\\Response\\ServerResponse',
            'Exception',
            'GuzzleHttp\\Psr7\\Response',
            'JsonException',
            'Psr\\Http\\Message\\ResponseInterface',
            'Psr\\Http\\Message\\ServerRequestInterface',
            'Spatial\\Psr7\\RequestHandler',
        ];

        if ($tracing) {
            $imports[] = 'OpenTelemetry\\API\\Trace\\StatusCode';
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
    ) {
        parent::__construct();
    }
PHP;
        } else {
            $constructor = <<<PHP
    public function __construct() {
        parent::__construct();
    }
PHP;
        }

        $tracingStart = '';
        $tracingEnd = '';
        $tracingErrorJson = '';
        $tracingErrorEx = '';
        
        if ($tracing) {
            $tracingStart = <<<PHP
        // Start tracing span
        \$span = \$this->tracer->spanBuilder('{$name}Handler::handle')->startSpan();
        \$scope = \$span->activate();
PHP;
            $tracingEnd = <<<PHP
            \$span->setStatus(StatusCode::STATUS_OK);
PHP;
            $tracingErrorJson = "\$span->recordException(\$e)->setStatus(StatusCode::STATUS_ERROR, 'JsonException');";
            $tracingErrorEx = "\$span->recordException(\$e)->setStatus(StatusCode::STATUS_ERROR, 'Exception');";
        }

        $loggingStart = $logging ? "\$this->logger->info('Starting {$name} query.');" : '';
        $loggingEnd = $logging ? "\$this->logger->info('{$name} completed successfully.');" : '';
        $loggingErrorJson = $logging ? "\$this->logger->error('JSON Exception in {$name}', ['exception' => \$e]);" : '';
        $loggingErrorEx = $logging ? "\$this->logger->error('Exception in {$name}', ['exception' => \$e]);" : '';

        $releaseCode = '';
        $finallyBlock = '';
        
        if ($releaseEntity || $tracing) {
            $finallyBlock = "finally {\n";
            if ($releaseEntity) {
                $finallyBlock .= "            // Release resources\n            \$request->releaseEntityManager();\n";
            }
            if ($tracing) {
                $finallyBlock .= "            \$scope->detach();\n            \$span->end();\n";
            }
            $finallyBlock .= "        }";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Queries;

use {$importsString};

/**
 * {$name} Handler
 * 
 * Handles the {$name} query.
 */
class {$name}Handler extends RequestHandler
{
{$constructor}

    /**
     * Handle the query request.
     *
     * @param {$name}|ServerRequestInterface \$request
     * @return ResponseInterface
     */
    public function handle({$name}|ServerRequestInterface \$request): ResponseInterface
    {
{$tracingStart}
{$loggingStart}

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

{$loggingEnd}
{$tracingEnd}

        } catch (JsonException \$e) {
            {$loggingErrorJson}
            {$tracingErrorJson}
            \$res->logError(
                code: '500',
                detail: ['reason' => 'JSON encoding error', 'exception' => \$e->getMessage()]
            );
        } catch (Exception \$e) {
            {$loggingErrorEx}
            {$tracingErrorEx}
            \$res->logError(
                code: '500',
                detail: ['reason' => \$e->getMessage()]
            );
        } {$finallyBlock}

        \$response = new Response();
        \$response = \$response->withStatus(\$res->getResponseStatus());
        \$response->getBody()->write((string)\$res);

        return \$response;
    }
}
PHP;
    }
}

