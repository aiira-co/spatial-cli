<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Generators;

use Spatial\Console\AbstractCommand;
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
class MakeControllerCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a new controller class in a module';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a controller name.");
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName>");
            $this->output("Example: php spatial make:controller User --module=IdentityApi");
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
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName>");
            $this->output("Example: php spatial make:controller Product --module=WebApi");
            return 1;
        }

        $module = $this->toPascalCase($module);

        // Generate controller
        $content = $this->generateController($name, $module);
        
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

    private function generateController(string $name, string $module): string
    {
        $shortName = str_replace('Controller', '', $name);
        $routeName = strtolower($shortName);
        $areaName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('Api', '-api', $module)));

        return <<<PHP
<?php

declare(strict_types=1);

namespace Presentation\\{$module}\\Controllers;

use Common\\Libraries\\Controller;
use Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Create{$shortName};
use Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Update{$shortName};
use Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Commands\\Delete{$shortName};
use Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Queries\\Get{$shortName};
use Core\\Application\\Logics\\{$shortName}\\{$shortName}\\Queries\\Get{$shortName}s;
use Psr\\Http\\Message\\ResponseInterface;
use Spatial\\Common\\BindSourceAttributes\\FromBody;
use Spatial\\Common\\HttpAttributes\\HttpDelete;
use Spatial\\Common\\HttpAttributes\\HttpGet;
use Spatial\\Common\\HttpAttributes\\HttpPost;
use Spatial\\Common\\HttpAttributes\\HttpPut;
use Spatial\\Core\\Attributes\\ApiController;
use Spatial\\Core\\Attributes\\Area;
use Spatial\\Core\\Attributes\\Authorize;
use Spatial\\Core\\Attributes\\Route;
use Infrastructure\\Services\\AuthenticationService;

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
{
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
     */
    #[HttpPost]
    #[Authorize(AuthenticationService::class)]
    public function create{$shortName}(#[FromBody] string \$content): ResponseInterface
    {
        \$command = new Create{$shortName}();
        \$command->data = json_decode(\$content, false, 512, JSON_THROW_ON_ERROR);
        \$command->appClaimParam = \$this->appClaims['{$routeName}'] ?? null;

        return \$this->mediator->process(\$command);
    }

    /**
     * PUT /{$areaName}/{$routeName}/{id}
     */
    #[HttpPut('{?id:int}')]
    #[Authorize(AuthenticationService::class)]
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
     */
    #[HttpDelete('{id:int}')]
    #[Authorize(AuthenticationService::class)]
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

