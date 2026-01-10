# Spatial CLI

Development tools for Spatial Framework - code generators, migrations, and development utilities.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## Installation

```bash
composer require spatial/cli --dev
```

## Commands (21)

### Generators (13)

| Command           | Description                 |
| ----------------- | --------------------------- |
| `make:controller` | Controller with Area + CQRS |
| `make:command`    | CQRS command + handler      |
| `make:query`      | CQRS query + handler        |
| `make:module`     | API module structure        |
| `make:dto`        | DTO with validation         |
| `make:entity`     | Doctrine entity             |
| `make:service`    | Infrastructure service      |
| `make:middleware` | PSR-15 middleware           |
| `make:trait`      | Domain DB trait             |
| `make:event`      | Domain event                |
| `make:listener`   | Event listener              |
| `make:seeder`     | Database seeder             |
| `make:job`        | Background job              |

### Database (4)

| Command          | Description      |
| ---------------- | ---------------- |
| `migrate:create` | Create migration |
| `migrate:run`    | Run migrations   |
| `migrate:status` | Migration status |
| `db:seed`        | Run seeders      |

### Quality (2)

| Command   | Description       |
| --------- | ----------------- |
| `lint`    | PSR-12 code style |
| `analyze` | PHPStan analysis  |

### Build (2)

| Command            | Description            |
| ------------------ | ---------------------- |
| `deploy:build`     | Package for production |
| `openapi:generate` | Generate OpenAPI spec  |

## Usage

```bash
# Generate a complete feature with full observability
php vendor/bin/spatial make:module OrdersApi
php vendor/bin/spatial make:entity Order --schema=Orders
php vendor/bin/spatial make:command CreateOrder --module=Orders --entity=Order --logging --tracing
php vendor/bin/spatial make:query GetOrders --module=Orders --entity=Order --logging --tracing --releaseEntity
php vendor/bin/spatial make:listener SendOrderEmail --event=OrderCreatedEvent --logging
php vendor/bin/spatial make:controller Order --module=OrdersApi --logging --auth

# Generate clean code without OTEL (minimal dependencies)
php vendor/bin/spatial make:query GetUsers --module=Identity --entity=User
php vendor/bin/spatial make:command UpdateProfile --module=Identity --entity=User
php vendor/bin/spatial make:controller Product --module=CatalogApi

# Generate middleware with observability
php vendor/bin/spatial make:middleware RateLimit --logging --tracing

# Generate background job with custom retry count
php vendor/bin/spatial make:job ProcessPayments --queue=payments --logging --tracing --retry=5

# Database operations
php vendor/bin/spatial migrate:create AddOrdersTable
php vendor/bin/spatial migrate:run
php vendor/bin/spatial db:seed

# Code quality
php vendor/bin/spatial lint --fix
php vendor/bin/spatial analyze --level=5

# Build for production
php vendor/bin/spatial deploy:build --output=dist
```

## Dry-Run Mode

Preview generated code without creating files:

```bash
# Preview what will be generated
php vendor/bin/spatial make:query GetUsers --module=Identity --entity=User --dry-run

# Preview with all flags
php vendor/bin/spatial make:controller Order --module=OrdersApi --logging --tracing --auth --dry-run

# Aliases: --dry-run, --preview
```

Output shows:

- File paths that would be created
- Line counts and file sizes
- First 20 lines of each file as preview
- Full content can be reviewed before committing

## Smart Error Messages

The CLI provides contextual help when errors occur:

**Typo Detection:**

```bash
php spatial make:query GetUsers --module=Identty --entity=User

# Output:
# ❌ Module 'Identty' not found.
#
# 💡 Suggestions:
#    • Did you mean: IdentityApi?
#    • Create the module first: php spatial make:module Identty
```

**Missing Parameters:**

```bash
php spatial make:query GetUsers --entity=User

# Output:
# ❌ Both --module and --entity parameters are required.
#
# 💡 Suggestions:
#    • Available modules: IdentityApi, OrdersApi, PaymentsApi
#
# 📖 Correct usage:
#    php spatial make:query <name> --module=<Module> --entity=<Entity>
```

## Optional Flags

All generators support optional flags for fine-grained control over generated code:

### Common Flags

- `--logging` - Include PSR-3 LoggerInterface dependency injection and logging calls
- `--tracing` - Include OpenTelemetry TracerInterface dependency injection and span tracking

### Generator-Specific Flags

- `make:query`, `make:command`
  - `--releaseEntity` - Add entity manager cleanup in finally block
- `make:controller`
  - `--auth` - Add #[Authorize] attributes to POST/PUT/DELETE endpoints
- `make:job`
  - `--retry=<number>` - Set custom retry attempts (default: 3)
  - `--queue=<name>` - Set queue name (default: default)
- `make:middleware`
  - `--folder=<name>` - Set folder name (default: Middlewares)

## Configuration

Create a `.spatial.yml` file in your project root to define default generator settings and avoid repetitive flag usage.

### Example Configuration

```yaml
generators:
  # Global defaults for all generators
  defaults:
    logging: true
    tracing: false
    releaseEntity: true

  # Override defaults for specific generators
  overrides:
    make:query:
      tracing: true # Always trace queries
    make:controller:
      auth: true # Controllers protected by default
    make:job:
      retry: 5 # Custom retry count
```

### Priority System

Configuration values are resolved in this order (highest to lowest):

1. **CLI Flags** - Explicit command-line arguments
2. **Generator Overrides** - Command-specific config (`make:query`, `make:command`, etc.)
3. **Global Defaults** - Project-wide defaults
4. **Hardcoded Defaults** - Built-in framework defaults

### Usage Examples

**With config file:**

```yaml
generators:
  defaults:
    logging: true
```

This command:

```bash
php spatial make:query GetUsers --module=Identity --entity=User
```

Automatically includes logging (from config), equivalent to:

```bash
php spatial make:query GetUsers --module=Identity --entity=User --logging
```

**Override config with CLI:**

```bash
# Config has logging: true, but CLI forces it off
php spatial make:query GetTest --module=Test --entity=Test
# (Remove logging from generated file manually or regenerate without config)
```

**See [.spatial.yml.example](file:///I:/Code/PHP/Frameworks/spatial-cli/.spatial.yml.example) for a complete configuration template.**

## Package Separation

This package contains **development tools only**.

| Package        | Commands                                 | Install       |
| -------------- | ---------------------------------------- | ------------- |
| `spatial/core` | 5 runtime (route:_, cache:_, queue:work) | `require`     |
| `spatial/cli`  | 21 development                           | `require-dev` |

## License

MIT - Created by [Kofi Owusu-Afriyie](https://aiira.co)
