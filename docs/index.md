---
layout: home

hero:
  name: Solo Container
  text: Lightweight PSR-11 container
  tagline: Factories, interface binding and constructor auto-wiring, each service built once and shared — and typed for static analysis down to get(Foo::class).
  image:
    src: /logo.svg
    alt: Solo Container
  actions:
    - theme: brand
      text: Get Started
      link: /guide/installation
    - theme: alt
      text: View on GitHub
      link: https://github.com/SoloPHP/Container

features:
  - icon: 🔌
    title: PSR-11
    details: 'Implements WritableContainerInterface from solophp/contracts, which extends Psr\Container\ContainerInterface with set(). Libraries depend on the PSR interface, the application registers the services.'
  - icon: 🪄
    title: Auto-wiring
    details: 'A class with no registration is built from its constructor: class and interface parameters come from the container, the rest take their defaults.'
  - icon: 🔗
    title: Interface binding
    details: 'bind(LoggerInterface::class, FileLogger::class) makes the interface resolve to the class, which must implement it, and both ids share one instance.'
  - icon: ♻️
    title: Shared instances
    details: 'Each id is built once and cached. An id has one registration: set() and bind() replace it together with its instance; reset() drops every instance.'
  - icon: 💤
    title: Lazy services
    details: 'lazy() for an id, or #[Lazy] for one parameter, injects a native PHP 8.4 lazy proxy that builds the service on first use — and breaks circular dependencies.'
  - icon: 🏷️
    title: Injection by id
    details: '#[Inject(''db.replica'')] resolves a parameter from a service id when its type cannot tell dependencies apart, and checks the value against the type.'
  - icon: 🔁
    title: Cycle detection
    details: 'A circular dependency in auto-wiring, bindings or factories fails at once and names the whole chain: A -> B -> A.'
  - icon: 🔍
    title: Guaranteed types
    details: 'get(Foo::class) returns a Foo or throws, and PHPStan sees it as a Foo at level max — through the container and through WritableContainerInterface. Any other id stays mixed.'
---

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
  --vp-home-hero-image-background-image: linear-gradient(135deg, #ec489930 0%, #8b5cf630 100%);
  --vp-home-hero-image-filter: blur(44px);
}

.VPHero .VPImage {
  max-width: 200px;
  max-height: 200px;
}
</style>

## Quick Example

```php
use Solo\Container\Attribute\Lazy;
use Solo\Container\Container;

final class UserRepository
{
    public function __construct(
        private Database $database,
        private LoggerInterface $logger,
        #[Lazy] private Mailer $mailer,
    ) {
    }
}

$container = new Container();

$container->set(Database::class, fn($c) => new Database($c->get(Config::class)->dsn));
$container->bind(LoggerInterface::class, FileLogger::class);

$users = $container->get(UserRepository::class);

// UserRepository — built from its constructor, also to PHPStan
// $database — from the factory, $logger — a FileLogger through the binding,
// $mailer — a proxy that builds the Mailer on first use
```

## Installation

```bash
composer require solophp/container
```

**Requirements:** PHP 8.4+.
