<?php

declare(strict_types=1);

namespace Highcore\Registry\Bundle\DependencyInjection\Pass;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @template A
 */
abstract class AbstractAttributeRegistryPass
{

    /**
     * @param class-string<A> $targetClassAttribute
     * @param class-string $definitionClass
     */
    public function __construct(
        private readonly string $targetClassAttribute,
        private readonly string $definition,
        private readonly string $definitionClass,
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

            $reflector = $container->getReflectionClass($definition->getClass(), false);
            $attributes = null === $reflector
                ? []
                : $reflector->getAttributes($this->targetClassAttribute, \ReflectionAttribute::IS_INSTANCEOF);

            if (!($reflector instanceof \ReflectionClass) || 0 === \count($attributes)) {
                continue;
            }

            if (null !== $this->interface && !$reflector->implementsInterface($this->interface)) {
                continue;
            }

            $this->processClass($container, $definition, $attributes);
        }
    }

    protected function setupRegistryDefinition(ContainerBuilder $container): void
    {
        $definitionArgs = null === $this->interface ? [] : [$this->interface];

        if (!$container->hasDefinition($this->definition)) {
            $container->setDefinition($this->definition, new Definition($this->definitionClass, $definitionArgs));
        }
    }

    protected function accept(Definition $definition): bool
    {
        return $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    abstract protected function processClass(ContainerBuilder $container, Definition $definition, array $attributes): void;
}