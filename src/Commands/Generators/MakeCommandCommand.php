<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

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
class MakeCommandCommand extends AbstractGenerator
{
    protected function getCommandName(): string
    {
        return 'make:command';
    }

    public function getName(): string
    {
        return $this->getCommandName();
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS command and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a command name.");
            $this->output("Usage: php spatial make:command <name> --module=<Module> --entity=<Entity> [--logging] [--tracing] [--releaseEntity]");
            $this->output("Example: php spatial make:command CreateUser --module=Identity --entity=User --logging --tracing");
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

        $commandContent = $this->generateCommand($name, $module, $entity);
        $handlerContent = $this->generateHandler(
            $name, 
            $module, 
            $entity, 
            $flags['logging'], 
            $flags['tracing'], 
            $flags['releaseEntity']
        );
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Commands";
        
        $commandPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        // Create command and handler files
        return $this->createFiles([
            [
                'path' => $commandPath,
                'content' => $commandContent,
                'type' => 'command'
            ],
            [
                'path' => $handlerPath,
                'content' => $handlerContent,
                'type' => 'handler'
            ]
        ]);
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

        $loggingStart = $logging ? "\$this->logger->info('Starting {$name} process.');" : '';
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

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Commands;

use {$importsString};

/**
 * {$name} Handler
 * 
 * Handles the {$name} command.
 */
class {$name}Handler extends RequestHandler
{
{$constructor}

    /**
     * Handle the command request.
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
            // Execute command logic
            \$result = \$request->execute();

            if (!(\$result['success'] ?? false)) {
                throw new Exception(\$result['message'] ?? 'Command execution failed');
            }

            \$res->data = \$result['data'] ?? null;
            \$res->message = \$result['message'] ?? '{$name} completed successfully';

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

