# Symfony Registry Bundle

**RegistryBundle** is a Symfony bundle that provides a convenient mechanism for working with registries. 
This package allows you to automatically register services with specific attributes and interfaces in registries.

## Installation
To install this package, use Composer:
```bash
composer require highcore/registry-bundle
```

## Configuration
After installation, add RegistryBundle to your Symfony configuration file (config/bundles.php):
```php
return [
    // ...
    Highcore\Bundle\RegistryBundle\RegistryBundle::class => ['all' => true],
];
```

## Usage
### Registering Registries
Registries are registered in the bundle class using a Compiler Pass. This allows services marked with attributes to be automatically registered in the appropriate registries during the container compilation phase.

Example of Registering a Registry
```php
<?php
declare(strict_types=1)

namespace App\YourBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Highcore\Bundle\RegistryBundle\Compiler\Pass\ServiceAttributeRegistryPass;
use Highcore\Component\Criteria\Doctrine\Handler\CriteriaRepository;
use Highcore\Bundle\RegistryBundle\Registry\IdentityServiceRegistry;
use Highcore\Bundle\RegistryBundle\Registry\ServiceRegistry;

class YourBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ServiceAttributeRegistryPass(
            definitionId: 'some.your.project.namespace.first.resource.registry',
            definitionClass: IdentityServiceRegistry::class,
            targetClassAttribute: \App\YourBundle\Attribute\AsYourResourceAttribute::class, // your attribute class
            interface: \App\YourBundle\YourServiceInterface::class, // your interface class (interface is optional, if passed, CompilerPass will check your service for an implementation of that interface)
        ));

        $container->addCompilerPass(new ServiceAttributeRegistryPass(
            definitionId: 'some.your.project.namespace.second.resource.registry',
            definitionClass: ServiceRegistry::class,
            targetClassAttribute: \App\YourBundle\Attribute\AsYourSecondResourceAttribute::class,
            // register registry without interface
        ));
    }
}
```

In this example, two registries are registered using CompilerPass:
### Registry for your first resource: Registers all services marked with the \App\AsYourResourceAttribute attribute, and each service must implement the \App\YourServiceInterface interface.
1. **Example Interface for your first registry:**
```php
<?php
declare(strict_types=1)

namespace App\YourBundle;

interface YourServiceInterface
{
    public function yourMethod(): void;
}
```
2. **Example Attribute for your first registry:**
```php
<?php
declare(strict_types=1)

namespace App\YourBundle\Attribute;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsYourResourceAttribute extends NamedArgumentConstructor implements IdentityServiceAttributeInterface
{
    public function __construct(private readonly ?string $identifier = null)
    {
    }

    // If self::hasIdentifier() returns false, this method will not be called,
    // instead we will take the name of the class to which this attribute will be assigned as the identifier
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function hasIdentifier(): bool
    {
        return null !== $this->identifier;
    }
}
```

### Registry for your second resource: Registers all services marked with the \App\AsYourSecondResourceAttribute attribute.
For example, for second registry we will create only attribute
```php
<?php
declare(strict_types=1)

namespace App;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AsYourSecondResourceAttribute
{
}
```

## Registering your service in the registry
Services can be automatically added to the registry using attributes. 
Simply add attributes to the service classes you want to register and register them in the symfony service container:
```php
<?php
declare(strict_types=1);

namespace App\YourBundle\Service;

use Highcore\JsonApi\Configurator\JsonApiResourceConfigurator;
use App\YourBundle\Attribute\AsYourResourceAttribute;
use App\YourBundle\YourServiceInterface;

#[AsYourResourceAttribute('some_identifier')]
class MyService implements YourServiceInterface
{
    public function yourMethod(): void
    {
        // Implementation of the configurator
    }
}
```

Register your service in the symfony container:
```php
# src/YourBundle/Resources/config/services.php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $defaults = $services->defaults();
    $defaults->autowire();
    $defaults->autoconfigure();

    // that's all you need to register your service in the registry
    $services->set(\App\YourBundle\Service\MyService::class);
};
```

## Using the registry
To get started, register your service and pass registry "some.your.project.namespace.first.resource.registry" to arguments
Take the ServiceRegistry service identifier from the definitionId used earlier in \App\YourBundle::build()
```php
# src/YourBundle/Resources/config/services.php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    // ...
    $services->set(\App\YourBundle\Service\SomeYourServiceUsingRegistry::class)
        ->args([service('some.your.project.namespace.first.resource.registry')]);
};
```

Declare your service
```php
<?php
declare(strict_types=1);

namespace App\YourBundle;

use Highcore\Component\Registry\IdentityServiceRegistryInterface;

final class SomeYourServiceUsingRegistry
{
    public function __construct(private readonly Highcore\Component\Registry\IdentityServiceRegistryInterface $registry)
    {
    }

    public function someMethod()
    {
        // Retrieve all registered services
        $yourServices = $this->registry->all();

        // Use the services
    }
```

