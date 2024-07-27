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

use Highcore\Component\Registry\Attribute\AttributeMethodReflection;
use Highcore\Component\Registry\Attribute\IdentityServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\PrioritizedServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
use Highcore\Component\Registry\IdentityPrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\IdentityServiceRegistryInterface;
use Highcore\Component\Registry\IdentitySinglePrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\ServiceRegistryInterface;
use Highcore\Component\Registry\SinglePrioritizedServiceRegistryInterface;
use Highcore\Registry\Bundle\Resolver\CallableIdentifierResolver;
use Spiral\Attributes\AttributeReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @template A
 * @template B
 */
final class CallableAttributeRegistryPass extends AbstractAttributeRegistryPass implements CompilerPassInterface
{
    public const COMPOUND_IDENTIFIER_ERROR = 'Attribute #[%s] should have identifier, because you don\'t have IdentifierResolver for your registry or set option "compoundAttributeIdentifier" to "false"';

    /**
     * @property class-string<A> $targetAttributeClass
     * @property class-string<B> $targetAttributeMethod
     */
    public function __construct(
        private readonly string $definitionId,
        private readonly string $definitionClass,
        private readonly string $targetClassAttribute,
        private readonly string $targetMethodAttribute,
        private readonly ?string $interface = null,
        private readonly bool $compoundAttributeIdentifier = false,
        private readonly null|CallableIdentifierResolver|\Closure $identifierResolver = null,
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

        static $attributeReader = new AttributeReader();
        $attributeMethodReflection = new AttributeMethodReflection($reflector, $attributeReader);
        foreach ($attributeMethodReflection->getMethodsHasAttribute($this->targetMethodAttribute) as $method) {
            $methodAttribute = $attributeReader->firstFunctionMetadata($method, $this->targetMethodAttribute);
            $identifier = $this->resolveIdentifier($reflector, $method, $methodAttribute, $classAttributeInstance);

            if (null === $identifier) {
                throw new \LogicException(
                    sprintf(
                        'Method "%s::%s" must implement "%s" or %s("%s") must have a resolver identifier.',
                        $reflector->getName(),
                        $method->getName(),
                        IdentityServiceAttributeInterface::class,
                        self::class,
                        $this->definitionId,
                    )
                );
            }

            $id = sprintf('%s.closure.instance', str_replace(['\\', '/'], '.', $identifier));
            $callableDefinition = $container
                ->setDefinition(
                    id: $id,
                    definition: new Definition(
                        class: \Closure::class,
                        arguments: [
                            [$definition, $method->getName()]
                        ],
                    )
                )
                ->setFactory([\Closure::class, 'fromCallable'])
            ;

            $registryDefinitionInterface = $this->getActualRegistryFromDefinition($this->definitionClass);

            /** @var IdentityServiceAttributeInterface|PrioritizedServiceAttributeInterface|ServiceAttributeInterface $classAttributeInstance */
            $arguments = match ($registryDefinitionInterface) {
                IdentitySinglePrioritizedServiceRegistryInterface::class,
                IdentityPrioritizedServiceRegistryInterface::class => [
                    $identifier,
                    $callableDefinition,
                    $classAttributeInstance->getPriority()
                ],
                SinglePrioritizedServiceRegistryInterface::class => [$callableDefinition, $classAttributeInstance->getPriority()],
                IdentityServiceRegistryInterface::class => [$identifier, $callableDefinition],
                ServiceRegistryInterface::class => [$callableDefinition],
            };

            $registryDefinition->addMethodCall('register', $arguments);
        }
    }

    public function resolveIdentifier(
        \ReflectionClass $reflector,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): ?string {
        if (null !== $this->identifierResolver) {
            return $this->resolveWithIdentifierResolver($reflector, $method, $methodAttribute, $classAttribute);
        }

        $baseIdentifier = $classAttribute instanceof IdentityServiceAttributeInterface && $classAttribute->hasIdentifier()
            ? $classAttribute->getIdentifier()
            : null;

        if (!$methodAttribute instanceof IdentityServiceAttributeInterface) {
            return $baseIdentifier;
        }

        if ($this->compoundAttributeIdentifier && !$methodAttribute->hasIdentifier()) {
            throw new \LogicException(sprintf(
                self::COMPOUND_IDENTIFIER_ERROR,
                get_class($methodAttribute),
            ));
        }

        return $methodAttribute->hasIdentifier()
            ? implode(':', [
                $baseIdentifier,
                $methodAttribute->getIdentifier(),
            ])
            : $baseIdentifier;
    }

    public function resolveWithIdentifierResolver(
        \ReflectionClass $reflector,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): string {
        return $this->identifierResolver instanceof \Closure
            ? (string) ($this->identifierResolver)(
                $reflector,
                $method,
                $methodAttribute,
                $classAttribute,
            )
            : $this->identifierResolver->resolve(
                class: $reflector,
                method: $method,
                methodAttribute: $methodAttribute,
                classAttribute: $classAttribute,
            );
    }
}
