<?php

declare(strict_types=1);

namespace Highcore\Registry\Symfony\DependencyInjection\Pass;

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

    protected function setupRegistryDefinition(ContainerBuilder $container): void
    {
        $definitionArgs = null === $this->targetClassAttribute ? [] : [$this->interface];

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