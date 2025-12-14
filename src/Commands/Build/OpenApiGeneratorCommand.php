<?php

declare(strict_types=1);

namespace Spatial\Cli\Commands\Build;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;
use ReflectionClass;
use ReflectionMethod;

/**
 * OpenAPI Generator Command
 * 
 * Generates OpenAPI 3.0 specification from controller attributes.
 * 
 * @example php spatial openapi:generate --output=docs/openapi.yaml
 * @example php spatial openapi:generate --format=json
 * 
 * @package Spatial\Console\Commands
 */
class OpenApiGeneratorCommand extends AbstractCommand
{
    private array $paths = [];
    private array $schemas = [];
    private array $tags = [];

    public function getName(): string
    {
        return 'openapi:generate';
    }

    public function getDescription(): string
    {
        return 'Generate OpenAPI 3.0 specification from controllers';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $output = $args['output'] ?? 'docs/openapi.yaml';
        $format = $args['format'] ?? (str_ends_with($output, '.json') ? 'json' : 'yaml');
        $title = $args['title'] ?? 'Spatial API';
        $version = $args['version'] ?? '1.0.0';

        $this->output("Scanning controllers...");

        // Scan presentation layer for controllers
        $presentationPath = $this->getBasePath() . '/src/presentation';
        if (!is_dir($presentationPath)) {
            $this->error("Presentation directory not found: {$presentationPath}");
            return 1;
        }

        $this->scanControllers($presentationPath);

        // Generate OpenAPI spec
        $spec = $this->generateSpec($title, $version);

        // Ensure output directory exists
        $this->ensureDirectory(dirname($this->getBasePath() . '/' . $output));

        $outputPath = $this->getBasePath() . '/' . $output;
        $content = $format === 'json' 
            ? json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $this->toYaml($spec);

        if ($this->writeFile($outputPath, $content)) {
            $this->success("Generated OpenAPI spec: {$outputPath}");
            $this->output("  Paths: " . count($this->paths));
            $this->output("  Tags: " . count($this->tags));
            return 0;
        }

        $this->error("Failed to write OpenAPI spec");
        return 1;
    }

    /**
     * Scan all controllers in the presentation layer.
     */
    private function scanControllers(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->parseControllerFile($file->getPathname());
            }
        }
    }

    /**
     * Parse a controller file and extract OpenAPI info.
     */
    private function parseControllerFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        // Extract namespace and class name
        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            return;
        }
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return;
        }

        $fqcn = $nsMatch[1] . '\\' . $classMatch[1];

        if (!class_exists($fqcn)) {
            // Try to load it
            require_once $filePath;
            if (!class_exists($fqcn)) {
                return;
            }
        }

        try {
            $reflection = new ReflectionClass($fqcn);
        } catch (\Exception $e) {
            return;
        }

        // Check if it's an API controller
        $apiControllerAttr = $reflection->getAttributes('Spatial\\Core\\Attributes\\ApiController');
        if (empty($apiControllerAttr)) {
            return;
        }

        $this->parseController($reflection);
    }

    /**
     * Parse controller class and extract routes.
     */
    private function parseController(ReflectionClass $reflection): void
    {
        $controllerName = $reflection->getShortName();
        $tagName = str_replace('Controller', '', $controllerName);

        // Add to tags
        $this->tags[$tagName] = [
            'name' => $tagName,
            'description' => "Operations for {$tagName}"
        ];

        // Get base route
        $baseRoute = $this->getBaseRoute($reflection);

        // Parse methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__construct' || $method->getName() === '__invoke') {
                continue;
            }

            $this->parseMethod($method, $baseRoute, $tagName);
        }
    }

    /**
     * Get base route from controller.
     */
    private function getBaseRoute(ReflectionClass $reflection): string
    {
        $route = '';
        $area = '';

        // Check for Area attribute
        $areaAttrs = $reflection->getAttributes('Spatial\\Core\\Attributes\\Area');
        if (!empty($areaAttrs)) {
            $area = $areaAttrs[0]->getArguments()[0] ?? '';
        }

        // Check for Route attribute
        $routeAttrs = $reflection->getAttributes('Spatial\\Core\\Attributes\\Route');
        if (!empty($routeAttrs)) {
            $route = $routeAttrs[0]->getArguments()[0] ?? $routeAttrs[0]->getArguments()['template'] ?? '';
        }

        // Replace placeholders
        $controllerName = strtolower(str_replace('Controller', '', $reflection->getShortName()));
        $route = str_replace('[controller]', $controllerName, $route);
        $route = str_replace('[area]', $area, $route);

        return '/' . trim($route, '/');
    }

    /**
     * Parse a controller method for HTTP endpoints.
     */
    private function parseMethod(ReflectionMethod $method, string $baseRoute, string $tag): void
    {
        $httpVerbs = [
            'Spatial\\Common\\HttpAttributes\\HttpGet' => 'get',
            'Spatial\\Common\\HttpAttributes\\HttpPost' => 'post',
            'Spatial\\Common\\HttpAttributes\\HttpPut' => 'put',
            'Spatial\\Common\\HttpAttributes\\HttpPatch' => 'patch',
            'Spatial\\Common\\HttpAttributes\\HttpDelete' => 'delete',
        ];

        foreach ($httpVerbs as $attrClass => $verb) {
            $attrs = $method->getAttributes($attrClass);
            if (empty($attrs)) {
                continue;
            }

            foreach ($attrs as $attr) {
                $template = $attr->getArguments()[0] ?? $attr->getArguments()['template'] ?? '';
                $path = $baseRoute . ($template ? '/' . $template : '');
                
                // Convert route params to OpenAPI format
                $path = preg_replace('/\{(\?)?(\w+):(\w+)\}/', '{$2}', $path);
                $path = preg_replace('/\{(\w+):(\w+)\}/', '{$1}', $path);
                $path = str_replace('//', '/', $path);

                $operation = $this->buildOperation($method, $tag, $path);

                if (!isset($this->paths[$path])) {
                    $this->paths[$path] = [];
                }
                $this->paths[$path][$verb] = $operation;
            }
        }
    }

    /**
     * Build OpenAPI operation from method.
     */
    private function buildOperation(ReflectionMethod $method, string $tag, string $path): array
    {
        $operation = [
            'tags' => [$tag],
            'summary' => $this->getMethodSummary($method),
            'operationId' => $method->getName(),
            'responses' => [
                '200' => [
                    'description' => 'Successful operation',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object']
                        ]
                    ]
                ]
            ]
        ];

        // Check for Authorize attribute
        $authAttrs = $method->getAttributes('Spatial\\Core\\Attributes\\Authorize');
        if (!empty($authAttrs)) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        // Extract path parameters
        if (preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            $operation['parameters'] = [];
            foreach ($matches[1] as $param) {
                $operation['parameters'][] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer']
                ];
            }
        }

        // Check for request body
        foreach ($method->getParameters() as $param) {
            $fromBodyAttrs = $param->getAttributes('Spatial\\Common\\BindSourceAttributes\\FromBody');
            if (!empty($fromBodyAttrs)) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object']
                        ]
                    ]
                ];
                break;
            }
        }

        return $operation;
    }

    /**
     * Get method summary from docblock.
     */
    private function getMethodSummary(ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();
        if (!$doc) {
            return ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $method->getName()));
        }

        // Extract first line of docblock
        if (preg_match('/\*\s+([^@\n]+)/', $doc, $match)) {
            return trim($match[1]);
        }

        return $method->getName();
    }

    /**
     * Generate the full OpenAPI spec.
     */
    private function generateSpec(string $title, string $version): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $title,
                'version' => $version,
                'description' => 'API generated by Spatial Framework'
            ],
            'servers' => [
                ['url' => 'http://localhost:8080', 'description' => 'Development server']
            ],
            'paths' => $this->paths,
            'tags' => array_values($this->tags),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ]
        ];
    }

    /**
     * Convert array to YAML string.
     */
    private function toYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $yaml .= $prefix . '- ';
                if (is_array($value)) {
                    $yaml .= "\n" . $this->toYaml($value, $indent + 1);
                } else {
                    $yaml .= $this->formatYamlValue($value) . "\n";
                }
            } else {
                $yaml .= $prefix . $key . ':';
                if (is_array($value)) {
                    if (empty($value)) {
                        $yaml .= " []\n";
                    } else {
                        $yaml .= "\n" . $this->toYaml($value, $indent + 1);
                    }
                } else {
                    $yaml .= ' ' . $this->formatYamlValue($value) . "\n";
                }
            }
        }

        return $yaml;
    }

    private function formatYamlValue(mixed $value): string
    {
        if ($value === null) return 'null';
        if ($value === true) return 'true';
        if ($value === false) return 'false';
        if (is_numeric($value)) return (string)$value;
        if (preg_match('/[:#\[\]{}|>&*!?]/', $value) || $value === '') {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return $value;
    }
}

