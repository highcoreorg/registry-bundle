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
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
use Highcore\Component\Registry\IdentityServiceRegistry;
use Highcore\Component\Registry\ServiceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @template A
 */
final class AttributeServiceRegistryPass implements CompilerPassInterface
{
    /**
     * @param class-string<A> $targetClassAttribute
     */
    public function __construct(
        private readonly string $definition,
        private readonly string $targetClassAttribute,
        private readonly bool $identifiable = false,
        private readonly ?string $interface = null,
    ) {
    }

    /**
     * @throws \ReflectionException
     */
    public function process(ContainerBuilder $container): void
    {
        $this->setupRegistryDefinition($container);

        foreach ($container->getDefinitions() as $definition) {
            if (!$this->accept($definition)) {
                continue;
            }

            $class = $container->getReflectionClass($definition->getClass(), false);
            $attributes = null === $class
                ? []
                : $class->getAttributes($this->targetClassAttribute, \ReflectionAttribute::IS_INSTANCEOF);

            if (!($class instanceof \ReflectionClass) || 0 === \count($attributes)) {
                continue;
            }

            $this->processClass($container, $definition, $attributes);
        }
    }

    public function accept(Definition $definition): bool
    {
        return $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    public function processClass(ContainerBuilder $container, Definition $definition, array $attributes): void
    {
        if (1 !== count($attributes)) {
            throw new \LogicException(sprintf('Attribute #[%s] should be declared once only.', $this->targetClassAttribute));
        }

        $classAttributeClass = \array_pop($attributes);
        if ($classAttributeClass->getTarget() !== \Attribute::TARGET_CLASS) {
            throw new \LogicException(sprintf(
                'Attribute "#[%s]" target should be only "%s::TARGET_CLASS"',
                $classAttributeClass->getName(), \Attribute::class
            ));
        }

        $reflector = new \ReflectionClass($definition->getClass());
        $classAttribute = $classAttributeClass->newInstance();

        if (!($classAttribute instanceof ServiceAttributeInterface)) {
            throw new \LogicException(sprintf(
                'Attribute #[%s] should implements "%s"',
                $classAttributeClass->getName(), ServiceAttributeInterface::class
            ));
        }

        $registryDefinition = $container->getDefinition($this->definition);
        $identifiable = $this->identifiable;

        $reference = new Reference($definition->innerServiceId ?? $definition->getClass());

        $identifier = $this->resolveIdentifier($reflector, $classAttribute);
        if (!$container->hasDefinition($identifier)) {
            $container->setDefinition($identifier, $definition);
            $reference = new Reference($identifier);
        }

        $arguments = !$identifiable ? [] : [$identifier];
        $registryDefinition->addMethodCall('register', [...$arguments, $reference]);
    }

    public function setupRegistryDefinition(ContainerBuilder $container): void
    {
        $definitionArgs = null === $this->targetClassAttribute ? [] : [$this->interface];
        $definitionClass = $this->identifiable ? IdentityServiceRegistry::class : ServiceRegistry::class;

        if (!$container->hasDefinition($this->definition)) {
            $container->setDefinition($this->definition, new Definition($definitionClass, $definitionArgs));
        }
    }

    private function resolveIdentifier(\ReflectionClass $reflector, object $classAttribute): string
    {
        if (!($classAttribute instanceof IdentityServiceAttributeInterface)) {
            return $reflector->getName();
        }

        if ($classAttribute->hasIdentifier()) {
            return $classAttribute->getIdentifier();
        }

        return $reflector->getName();
    }
}