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

use Highcore\Component\Registry\Attribute\AttributeMethodReflection;
use Highcore\Component\Registry\Attribute\IdentityServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
use Highcore\Component\Registry\CallableServiceRegistry;
use Highcore\Registry\Symfony\Resolver\CallableRegistryIdentifierResolver;
use Spiral\Attributes\AttributeReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @template A
 * @template B
 */
final class AttributeCallableRegistryPass implements CompilerPassInterface
{
    /**
     * @property class-string<A> $targetAttributeClass
     * @property class-string<B> $targetAttributeMethod
     */
    public function __construct(
        private readonly string $definition,
        private readonly string $targetClassAttribute,
        private readonly string $targetMethodAttribute,
        private readonly ?string $interface = null,
        private readonly null|CallableRegistryIdentifierResolver|\Closure $identifierResolver = null,
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

        $registryDefinition = $container->getDefinition($this->definition);
        $reflector = new \ReflectionClass($definition->getClass());
        $classAttribute = $classAttributeClass->newInstance();

        if (!($classAttribute instanceof ServiceAttributeInterface)) {
            throw new \LogicException(sprintf(
                'Attribute #[%s] should implements "%s"',
                $classAttributeClass->getName(), ServiceAttributeInterface::class
            ));
        }

        static $attributeReader = new AttributeReader();
        $attributeMethodReflection = new AttributeMethodReflection($reflector, $attributeReader);

        foreach ($attributeMethodReflection->getMethodsHasAttribute($this->targetMethodAttribute) as $method) {
            $methodAttribute = $attributeReader->firstFunctionMetadata($method, $this->targetMethodAttribute);
            $identifier = $this->resolveIdentifier($reflector, $method, $methodAttribute, $classAttribute);

            if (null === $identifier) {
                throw new \LogicException(sprintf(
                    'Method "%s::%s" must implement "%s" or %s("%s") must have a resolver identifier.',
                    $reflector->getName(), $method->getName(), IdentityServiceAttributeInterface::class,
                    self::class, $this->definition,
                ));
            }

            $registryDefinition->addMethodCall('register', [
                $identifier, $definition, $method->getName(),
            ]);
        }
    }

    public function setupRegistryDefinition(ContainerBuilder $container): void
    {
        $definitionArgs = null === $this->targetClassAttribute ? [] : [$this->interface];

        if (!$container->hasDefinition($this->definition)) {
            $container->setDefinition($this->definition, new Definition(CallableServiceRegistry::class, $definitionArgs));
        }
    }

    public function resolveIdentifier(
        \ReflectionClass $reflector,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): ?string {
        if ($methodAttribute instanceof IdentityServiceAttributeInterface && $methodAttribute->hasIdentifier()) {
            return $this->handleIdentifier($methodAttribute->getIdentifier());
        }

        if (null !== $this->identifierResolver) {
            return $this->handleIdentifier(
                $this->identifierResolver instanceof \Closure
                    ? (string) $this->identifierResolver->call(
                        $this,
                        $reflector,
                        $method,
                        $methodAttribute,
                        $classAttribute
                    )
                    : $this->identifierResolver->resolve(
                        class: $reflector,
                        method: $method,
                        methodAttribute: $methodAttribute,
                        classAttribute: $classAttribute,
                    )
            );
        }

        return null;
    }

    private function handleIdentifier(string $identifier): string
    {
        if (!class_exists($identifier)) {
            throw new \LogicException(\sprintf('Identifier "%s" should be of Command class.', $identifier));
        }

        return $identifier;
    }
}