# Solo PHP Container

A lightweight, PSR-11 compliant dependency injection container for PHP applications.

## Installation

You can install the package via composer:

```bash
composer require solophp/container
```

## Requirements

- PHP 8.0 or higher
- Composer 2.0 or higher

## Basic Usage

```php
use Solo\Container;

// Create a new container
$container = new Container();

// Register a service
$container->set('database', function($container) {
    return new Database('localhost', 'mydb', 'user', 'pass');
});

// Bind an interface to a concrete implementation
$container->bind(LoggerInterface::class, FileLogger::class);

// Get a service
$db = $container->get('database');
```

## Features

- PSR-11 Compatible
- Automatic dependency resolution
- Interface binding
- Singleton instances
- Constructor injection
- Service factories

## Advanced Usage

### Auto-Resolution

The container can automatically resolve class dependencies:

```php
class UserRepository
{
    public function __construct(
        private Database $database,
        private LoggerInterface $logger
    ) {}
}

// The container will automatically resolve Database and LoggerInterface
$userRepo = $container->get(UserRepository::class);
```

### Multiple Services Registration

```php
$container = new Container([
    'config' => fn() => new Config('config.php'),
    'cache' => fn($c) => new Cache($c->get('config')),
]);
```

### Interface Binding

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->bind(CacheInterface::class, RedisCache::class);
```

## Error Handling

The container throws two types of exceptions:

- `Solo\Exceptions\NotFoundException`: When a requested service is not found
- `Solo\Exceptions\ContainerException`: When there's an error resolving a service

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.