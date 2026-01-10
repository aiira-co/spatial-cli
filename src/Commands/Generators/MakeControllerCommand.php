<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\Application;

/**
 * Make Controller Command
 * 
 * Generates a new API controller and registers it in the module.
 * 
 * @example php spatial make:controller User --module=IdentityApi
 * @example php spatial make:controller Product --module=WebApi
 * 
 * @package Spatial\Console\Commands
 */
class MakeControllerCommand extends AbstractGenerator
{
    protected function getCommandName(): string
    {
        return 'make:controller';
    }

    public function getName(): string
    {
        return $this->getCommandName();
    }

    public function getDescription(): string
    {
        return 'Create a new controller class in a module';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;
        $this->dryRun = $this->isDryRun($args);

        if (empty($args['_positional'])) {
            $this->error("Please provide a controller name.");
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName> [--logging] [--tracing] [--auth]");
            $this->output("Example: php spatial make:controller User --module=IdentityApi --logging --auth");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        // Module is required
        $module = $args['module'] ?? null;
        if ($module === null) {
            $this->error("--module parameter is required.");
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName> [--logging] [--tracing] [--auth]");
            $this->output("Example: php spatial make:controller Product --module=WebApi");
            return 1;
        }

        $module = $this->toPascalCase($module);

        // Load config defaults
        $configDefaults = ConfigLoader::getGeneratorDefaults($this->getBasePath(), 'make:controller');

        // Parse optional flags (CLI args override config)
        $logging = isset($args['logging']) ? true : ($configDefaults['logging'] ?? false);
        $tracing = isset($args['tracing']) ? true : ($configDefaults['tracing'] ?? false);
        $auth = isset($args['auth']) ? true : ($configDefaults['auth'] ?? false);

        // Generate controller
        $content = $this->generateController($name, $module, $logging, $tracing, $auth);
        
        $controllerDir = $this->getBasePath() . "/src/presentation/{$module}/Controllers";
        $controllerPath = "{$controllerDir}/{$name}.php";
        
        if (file_exists($controllerPath)) {
            $this->error("Controller already exists: {$controllerPath}");
            return 1;
        }

        // Ensure Controllers directory exists
        $this->ensureDirectory($controllerDir);

        if (!$this->writeFile($controllerPath, $content)) {
            $this->error("Failed to create controller");
            return 1;
        }

        $this->success("Created controller: {$controllerPath}");

        // Auto-register in module
        $registered = $this->registerInModule($name, $module);
        if ($registered) {
            $this->success("Registered in {$module}Module.php");
        } else {
            $this->output("Note: Could not auto-register. Please add to {$module}Module.php manually.");
        }

        return 0;
    }

    private function generateController(
        string $name, 
        string $module,
        bool $logging = false,
        bool $tracing = false,
        bool $auth = false
    ): string {
        $shortName = str_replace('Controller', '', $name);
        $routeName = strtolower($shortName);
        $areaName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('Api', '-api', $module)));

        // Build imports
        $imports = [
            "Common\\Libraries\\Controller",
            "Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Create{$shortName}",
            "Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Update{$shortName}",
            "Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Delete{$shortName}",
            "Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Queries\\Get{$shortName}",
            "Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Queries\\Get{$shortName}s",
            "Psr\\Http\\Message\\ResponseInterface",
            "Spatial\\Common\\BindSourceAttributes\\FromBody",
            "Spatial\\Common\\HttpAttributes\\HttpDelete",
            "Spatial\\Common\\HttpAttributes\\HttpGet",
            "Spatial\\Common\\HttpAttributes\\HttpPost",
            "Spatial\\Common\\HttpAttributes\\HttpPut",
            "Spatial\\Core\\Attributes\\ApiController",
            "Spatial\\Core\\Attributes\\Area",
            "Spatial\\Core\\Attributes\\Route",
        ];

        if ($auth) {
            $imports[] = "Spatial\\Core\\Attributes\\Authorize";
            $imports[] = "Infrastructure\\Services\\AuthenticationService";
        }

        if ($logging) {
            $imports[] = "Psr\\Log\\LoggerInterface";
        }

        if ($tracing) {
            $imports[] = "OpenTelemetry\\API\\Trace\\TracerInterface";
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

        $constructor = '';
        if (!empty($constructorParams)) {
            $params = implode(",\n        ", $constructorParams);
            $constructor = "\n\n    public function __construct(\n        {$params}\n    ) {\n        parent::__construct();\n    }";
        }

        // Authorization attributes
        $authAttr = $auth ? "\n    #[Authorize(AuthenticationService::class)]" : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace Presentation\\{$module}\\Controllers;

use {$importsString};

/**
 * {$name}
 * 
 * Handles {$shortName} API endpoints.
 * 
 * @category Controller
 */
#[ApiController]
#[Area('{$areaName}')]
#[Route('[area]/[controller]')]
class {$name} extends Controller
{{$constructor}
    /**
     * GET /{$areaName}/{$routeName}
     */
    #[HttpGet]
    public function get{$shortName}s(): ResponseInterface
    {
        \$query = new Get{$shortName}s();
        \$query->params = \$this->params;
        \$query->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$query);
    }

    /**
     * GET /{$areaName}/{$routeName}/{id}
     */
    #[HttpGet('{id:int}')]
    public function get{$shortName}(int \$id): ResponseInterface
    {
        \$query = new Get{$shortName}();
        \$query->id = \$id;
        \$query->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$query);
    }

    /**
     * POST /{$areaName}/{$routeName}
     */{$authAttr}
    #[HttpPost]
    public function create{$shortName}(#[FromBody] string \$content): ResponseInterface
    {
        \$command = new Create{$shortName}();
        \$command->data = json_decode(\$content, false, 512, JSON_THROW_ON_ERROR);
        \$command->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$command);
    }

    /**
     * PUT /{$areaName}/{$routeName}/{id}
     */{$authAttr}
    #[HttpPut('{?id:int}')]
    public function update{$shortName}(#[FromBody] string \$content, int \$id): ResponseInterface
    {
        \$command = new Update{$shortName}();
        \$command->id = \$id;
        \$command->data = json_decode(\$content, false, 512, JSON_THROW_ON_ERROR);
        \$command->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$command);
    }

    /**
     * DELETE /{$areaName}/{$routeName}/{id}
     */{$authAttr}
    #[HttpDelete('{id:int}')]
    public function delete{$shortName}(int \$id): ResponseInterface
    {
        \$command = new Delete{$shortName}();
        \$command->id = \$id;
        \$command->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$command);
    }
}
PHP;
    }

    /**
     * Register controller in module file.
     */
    private function registerInModule(string $controllerName, string $module): bool
    {
        $modulePath = $this->getBasePath() . "/src/presentation/{$module}/{$module}Module.php";
        
        if (!file_exists($modulePath)) {
            return false;
        }

        $content = file_get_contents($modulePath);
        
        // Check if already registered
        if (str_contains($content, "{$controllerName}::class")) {
            return true; // Already registered
        }

        // Add use statement
        $useStatement = "use Presentation\\{$module}\\Controllers\\{$controllerName};";
        if (!str_contains($content, $useStatement)) {
            // Find last use statement and add after it
            $content = preg_replace(
                '/(use [^;]+;\n)(?!use )/',
                "$1{$useStatement}\n",
                $content,
                1
            );
        }

        // Add to declarations array
        $content = preg_replace(
            '/(declarations:\s*\[\s*)(\n?\s*)([^\]]*)(])/',
            "$1$2$3    {$controllerName}::class,\n$4",
            $content
        );

        return file_put_contents($modulePath, $content) !== false;
    }
}

