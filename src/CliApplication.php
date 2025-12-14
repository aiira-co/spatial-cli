<?php

declare(strict_types=1);

namespace Spatial\Cli;

use Spatial\Console\Application;
use Spatial\Console\CommandInterface;

/**
 * CLI Application
 * 
 * Extends the core Application with development commands.
 * 
 * @package Spatial\Cli
 */
class CliApplication extends Application
{
    private string $version = '1.0.0';

    public function __construct()
    {
        parent::__construct();
        $this->registerCliCommands();
    }

    /**
     * Register CLI-specific commands.
     */
    private function registerCliCommands(): void
    {
        // Generators
        $this->register(new Commands\Generators\MakeControllerCommand());
        $this->register(new Commands\Generators\MakeCommandCommand());
        $this->register(new Commands\Generators\MakeQueryCommand());
        $this->register(new Commands\Generators\MakeModuleCommand());
        $this->register(new Commands\Generators\MakeDtoCommand());
        $this->register(new Commands\Generators\MakeEntityCommand());
        $this->register(new Commands\Generators\MakeServiceCommand());
        $this->register(new Commands\Generators\MakeMiddlewareCommand());
        $this->register(new Commands\Generators\MakeTraitCommand());
        $this->register(new Commands\Generators\MakeEventCommand());
        $this->register(new Commands\Generators\MakeListenerCommand());
        $this->register(new Commands\Generators\MakeSeederCommand());
        $this->register(new Commands\Generators\MakeJobCommand());

        // Database
        $this->register(new Commands\Database\MigrateCreateCommand());
        $this->register(new Commands\Database\MigrateRunCommand());
        $this->register(new Commands\Database\MigrateStatusCommand());
        $this->register(new Commands\Database\DbSeedCommand());

        // Code Quality
        $this->register(new Commands\Quality\LintCommand());
        $this->register(new Commands\Quality\AnalyzeCommand());

        // Build
        $this->register(new Commands\Build\DeployBuildCommand());
        $this->register(new Commands\Build\OpenApiGeneratorCommand());
    }
}
