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

| Command | Description |
|---------|-------------|
| `make:controller` | Controller with Area + CQRS |
| `make:command` | CQRS command + handler |
| `make:query` | CQRS query + handler |
| `make:module` | API module structure |
| `make:dto` | DTO with validation |
| `make:entity` | Doctrine entity |
| `make:service` | Infrastructure service |
| `make:middleware` | PSR-15 middleware |
| `make:trait` | Domain DB trait |
| `make:event` | Domain event |
| `make:listener` | Event listener |
| `make:seeder` | Database seeder |
| `make:job` | Background job |

### Database (4)

| Command | Description |
|---------|-------------|
| `migrate:create` | Create migration |
| `migrate:run` | Run migrations |
| `migrate:status` | Migration status |
| `db:seed` | Run seeders |

### Quality (2)

| Command | Description |
|---------|-------------|
| `lint` | PSR-12 code style |
| `analyze` | PHPStan analysis |

### Build (2)

| Command | Description |
|---------|-------------|
| `deploy:build` | Package for production |
| `openapi:generate` | Generate OpenAPI spec |

## Usage

```bash
# Generate a complete feature
php vendor/bin/spatial make:module OrdersApi
php vendor/bin/spatial make:entity Order --schema=Orders
php vendor/bin/spatial make:command CreateOrder --module=Orders --entity=Order
php vendor/bin/spatial make:controller Order --module=OrdersApi

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

## Package Separation

This package contains **development tools only**.

| Package | Commands | Install |
|---------|----------|---------|
| `spatial/core` | 5 runtime (route:*, cache:*, queue:work) | `require` |
| `spatial/cli` | 21 development | `require-dev` |

## License

MIT - Created by [Kofi Owusu-Afriyie](https://aiira.co)
