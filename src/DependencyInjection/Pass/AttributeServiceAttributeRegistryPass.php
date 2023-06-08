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

namespace Highcore\Registry\Symfony\DependencyInjection\Pass;

use Highcore\Component\Registry\Attribute\IdentityServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\PrioritizedServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
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
final class AttributeServiceAttributeRegistryPass extends AbstractAttributeRegistryPass implements CompilerPassInterface
{
    public const DEFAULT_REGISTRIES = [
        IdentitySinglePrioritizedServiceRegistryInterface::class,
        SinglePrioritizedServiceRegistryInterface::class,
        IdentityServiceRegistryInterface::class,
        ServiceRegistryInterface::class,
    ];

    public const REGISTRY_REQUIRED_ATTRIBUTE_MAP = [
        [
            [SinglePrioritizedServiceRegistryInterface::class, IdentitySinglePrioritizedServiceRegistryInterface::class],
            PrioritizedServiceAttributeInterface::class,
        ],
        [
            [IdentityServiceRegistryInterface::class, ServiceRegistryInterface::class],
            ServiceAttributeInterface::class
        ],
    ];

    /**
     * @param class-string<A> $targetClassAttribute
     * @param class-string $definitionClass
     */
    public function __construct(
        private readonly string $definition,
        private readonly string $targetClassAttribute,
        private readonly string $definitionClass = ServiceRegistry::class,
        private readonly ?string $interface = null,
    ) {
        parent::__construct(
            targetClassAttribute: $this->targetClassAttribute,
            definition: $this->definition,
            definitionClass: $this->definitionClass,
            interface: $this->interface,
        );
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    public function processClass(ContainerBuilder $container, Definition $definition, array $attributes): void
    {
        if (1 !== count($attributes)) {
            throw new \LogicException(sprintf('Attribute #[%s] should be declared once only.',
                $this->targetClassAttribute));
        }

        $classAttributeClass = \array_pop($attributes);
        if ($classAttributeClass->getTarget() !== \Attribute::TARGET_CLASS) {
            throw new \LogicException(sprintf(
                'Attribute "#[%s]" target should be only "%s::TARGET_CLASS"',
                $classAttributeClass->getName(), \Attribute::class
            ));
        }

        $reflector = new \ReflectionClass($definition->getClass());
        $attributeInstance = $classAttributeClass->newInstance();
        $this->validateAttributeWithRegistryDefinition($attributeInstance);

        if (!($attributeInstance instanceof ServiceAttributeInterface)) {
            throw new \LogicException(sprintf(
                'Attribute #[%s] should implements "%s"',
                $classAttributeClass->getName(), ServiceAttributeInterface::class
            ));
        }

        $registryDefinition = $container->getDefinition($this->definition);
        $reference = new Reference($definition->innerServiceId ?? $definition->getClass());
        $identifier = $this->resolveIdentifier($reflector, $attributeInstance);

        if (!$container->hasDefinition($identifier)) {
            $container->setDefinition($identifier, $definition);
            $reference = new Reference($identifier);
        }

        $registryDefinitionInterface = $this->getActualRegistryFromDefinition($this->definitionClass);

        /** @var IdentityServiceAttributeInterface|PrioritizedServiceAttributeInterface|ServiceAttributeInterface $attributeInstance */
        $arguments = match ($registryDefinitionInterface) {
            IdentitySinglePrioritizedServiceRegistryInterface::class => [$identifier, $reference, $attributeInstance->getPriority()],
            SinglePrioritizedServiceRegistryInterface::class => [$reference, $attributeInstance->getPriority()],
            IdentityServiceRegistryInterface::class => [$identifier, $reference],
            ServiceRegistryInterface::class => [$reference],
        };

        $registryDefinition->addMethodCall('register', $arguments);
    }

    private function resolveIdentifier(\ReflectionClass $reflector, object $classAttribute): string
    {
        if (!($classAttribute instanceof IdentityServiceAttributeInterface) || !$classAttribute->hasIdentifier()) {
            return $reflector->getName();
        }

        return $classAttribute->getIdentifier();
    }

    /** @noinspection NotOptimalIfConditionsInspection */
    private function validateAttributeWithRegistryDefinition(object $attributeInstance): void
    {
        $registry = $this->getActualRegistryFromDefinition($this->definitionClass);

        foreach (self::REGISTRY_REQUIRED_ATTRIBUTE_MAP as [$registries, $requiredAttributeInterface]) {
            if (in_array($registry, $registries, true) && !(
                    $attributeInstance instanceof $requiredAttributeInterface
                )) {
                throw new \LogicException(\sprintf(
                    'Attribute #[%s] should implements "%s"',
                    get_class($attributeInstance),
                    PrioritizedServiceAttributeInterface::class
                ));
            }
        }
    }

    private function getActualRegistryFromDefinition(string $definitionClass): string
    {
        foreach (self::DEFAULT_REGISTRIES as $registry) {
            if (is_a($definitionClass, $registry, true)) {
                return $registry;
            }
        }

        throw new \LogicException(\sprintf(
            'Class "%s::class" does not implement any available registry type: [%s]',
            $definitionClass, implode(', ', self::DEFAULT_REGISTRIES)
        ));
    }
}