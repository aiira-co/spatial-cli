<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Build;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Deploy Build Command
 * 
 * Packages the project for production deployment.
 * 
 * @example php spatial deploy:build --output=dist
 * @example php spatial deploy:build --output=dist --no-dev
 * 
 * @package Spatial\Console\Commands
 */
class DeployBuildCommand extends AbstractCommand
{
    private array $excludePatterns = [
        '.git',
        '.gitignore',
        '.env',
        '.idea',
        '.vscode',
        '.vs',
        'node_modules',
        'tests',
        'var/cache',
        'var/log',
        'var/migrations',
        '*.md',
        'docker-compose*',
        'phpunit.xml',
        'pest.xml',
        'phpcs.xml',
        'phpstan.neon',
        '.DS_Store',
    ];

    public function getName(): string
    {
        return 'deploy:build';
    }

    public function getDescription(): string
    {
        return 'Package project for production deployment';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $output = $args['output'] ?? 'dist';
        $noDev = isset($args['no-dev']) || isset($args['production']);
        $generateDockerfile = !isset($args['no-docker']);

        $outputPath = $this->getBasePath() . '/' . $output;

        $this->output("Building production package...");
        $this->output("");

        // Step 1: Clean output directory
        $this->output("1. Preparing output directory...");
        if (is_dir($outputPath)) {
            $this->deleteDirectory($outputPath);
        }
        $this->ensureDirectory($outputPath);
        $this->success("   Output directory ready: {$outputPath}");

        // Step 2: Copy source files
        $this->output("2. Copying source files...");
        $copied = $this->copyProjectFiles($this->getBasePath(), $outputPath);
        $this->success("   Copied {$copied} files");

        // Step 3: Generate route cache
        $this->output("3. Generating route cache...");
        $this->generateRouteCache($outputPath);
        $this->success("   Route cache generated");

        // Step 4: Optimize composer
        if ($noDev) {
            $this->output("4. Optimizing composer (no-dev)...");
            $this->output("   Run in output directory:");
            $this->output("   composer install --no-dev --optimize-autoloader --classmap-authoritative");
        } else {
            $this->output("4. Skipping composer optimization (add --no-dev for production)");
        }

        // Step 5: Generate Dockerfile
        if ($generateDockerfile) {
            $this->output("5. Generating production Dockerfile...");
            $this->generateDockerfile($outputPath);
            $this->success("   Dockerfile generated");
        }

        // Step 6: Generate deployment manifest
        $this->output("6. Generating deployment manifest...");
        $this->generateManifest($outputPath);
        $this->success("   Manifest generated");

        $this->output("");
        $this->success("Build complete!");
        $this->output("");
        $this->output("Next steps:");
        $this->output("  cd {$output}");
        if ($noDev) {
            $this->output("  composer install --no-dev --optimize-autoloader --classmap-authoritative");
        }
        $this->output("  docker build -t my-api .");
        $this->output("  docker run -p 8080:8080 my-api");

        return 0;
    }

    private function copyProjectFiles(string $source, string $dest): int
    {
        $copied = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            
            // Check exclusions
            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            $destPath = $dest . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
                $copied++;
            }
        }

        return $copied;
    }

    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (str_starts_with($pattern, '*')) {
                $ext = substr($pattern, 1);
                if (str_ends_with($path, $ext)) {
                    return true;
                }
            } elseif (str_contains($path, $pattern)) {
                return true;
            }
        }

        // Exclude vendor but keep it referenced for composer
        if (str_starts_with($path, 'vendor/')) {
            return true;
        }

        return false;
    }

    private function generateRouteCache(string $outputPath): void
    {
        $cacheDir = $outputPath . '/var/cache';
        $this->ensureDirectory($cacheDir);

        // Simplified route cache generation
        $cacheContent = <<<'PHP'
<?php
/**
 * Route Cache
 * Generated for production deployment
 */
return [];
PHP;

        file_put_contents($cacheDir . '/routes.cache.php', $cacheContent);
    }

    private function generateDockerfile(string $outputPath): void
    {
        $dockerfile = <<<'DOCKERFILE'
# Production Dockerfile
# Generated by: php spatial deploy:build

FROM phpswoole/swoole:php8.2-alpine

WORKDIR /app

# Install extensions
RUN docker-php-ext-install pdo pdo_pgsql pcntl bcmath

# Copy application
COPY . /app

# Install dependencies (production only)
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Start server
CMD ["php", "public/index.php"]
DOCKERFILE;

        file_put_contents($outputPath . '/Dockerfile', $dockerfile);

        // Also generate .dockerignore
        $dockerignore = <<<'IGNORE'
.git
.gitignore
.env
.idea
.vscode
tests
var/log
var/cache
*.md
docker-compose*
phpunit.xml
IGNORE;

        file_put_contents($outputPath . '/.dockerignore', $dockerignore);
    }

    private function generateManifest(string $outputPath): void
    {
        $manifest = [
            'name' => 'spatial-api',
            'version' => '1.0.0',
            'generated_at' => date('c'),
            'php_version' => PHP_VERSION,
            'framework' => 'spatial',
            'entry_point' => 'public/index.php',
            'port' => 8080
        ];

        file_put_contents(
            $outputPath . '/deploy-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}

