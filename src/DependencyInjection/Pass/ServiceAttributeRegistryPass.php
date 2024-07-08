<?php

declare(strict_types=1);

/*
 * This file is part of the Highcore group.
 *
 * (c) Roman Cherniakhovsky <bizrenay@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Highcore\Registry\Bundle\DependencyInjection\Pass;

use Highcore\Component\Registry\Attribute\IdentityServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\PrioritizedServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
use Highcore\Component\Registry\IdentityPrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\IdentityServiceRegistryInterface;
use Highcore\Component\Registry\IdentitySinglePrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\ServiceRegistry;
use Highcore\Component\Registry\ServiceRegistryInterface;
use Highcore\Component\Registry\SinglePrioritizedServiceRegistryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @template A
 */
final class ServiceAttributeRegistryPass extends AbstractAttributeRegistryPass implements CompilerPassInterface
{

    /**
     * @param class-string<A> $targetClassAttribute
     * @param class-string $definitionClass
     */
    public function __construct(
        private readonly string $definitionId,
        private readonly string $definitionClass,
        private readonly string $targetClassAttribute,
        private readonly ?string $interface = null,
    ) {
        parent::__construct(
            targetClassAttribute: $this->targetClassAttribute,
            definitionId: $this->definitionId,
            definitionClass: $this->definitionClass,
            interface: $this->interface,
        );
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    public function processClass(
        \ReflectionClass $reflector,
        object $classAttributeInstance,
        Definition $registryDefinition,
        ContainerBuilder $container,
        Definition $definition,
        array $attributes
    ): void {
        $this->validateAttributeWithRegistryDefinition($classAttributeInstance);
        $identifier = $this->resolveIdentifier($reflector, $classAttributeInstance);

        $registryDefinitionInterface = $this->getActualRegistryFromDefinition($this->definitionClass);

        /** @var IdentityServiceAttributeInterface|PrioritizedServiceAttributeInterface|ServiceAttributeInterface $classAttributeInstance */
        $arguments = match ($registryDefinitionInterface) {
            IdentitySinglePrioritizedServiceRegistryInterface::class,
            IdentityPrioritizedServiceRegistryInterface::class => [
                $identifier,
                $definition,
                $classAttributeInstance->getPriority()
            ],
            SinglePrioritizedServiceRegistryInterface::class => [$definition, $classAttributeInstance->getPriority()],
            IdentityServiceRegistryInterface::class => [$identifier, $definition],
            ServiceRegistryInterface::class => [$definition],
        };

        $registryDefinition->addMethodCall('register', $arguments);
    }

    private function resolveIdentifier(\ReflectionClass $reflector, object $classAttributeInstance): string
    {
        if (!($classAttributeInstance instanceof IdentityServiceAttributeInterface) || !$classAttributeInstance->hasIdentifier()) {
            return $reflector->getName();
        }

        return $classAttributeInstance->getIdentifier();
    }
}