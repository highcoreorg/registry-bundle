<?php

declare(strict_types=1);

namespace Highcore\Registry\Bundle\DependencyInjection\Pass;

use Highcore\Component\Registry\Attribute\IdentityServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\PrioritizedServiceAttributeInterface;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;
use Highcore\Component\Registry\IdentityPrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\IdentityServiceRegistryInterface;
use Highcore\Component\Registry\IdentitySinglePrioritizedServiceRegistryInterface;
use Highcore\Component\Registry\ServiceRegistryInterface;
use Highcore\Component\Registry\SinglePrioritizedServiceRegistryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @template A
 */
abstract class AbstractAttributeRegistryPass
{

    public const DEFAULT_REGISTRIES = [
        IdentitySinglePrioritizedServiceRegistryInterface::class,
        IdentityPrioritizedServiceRegistryInterface::class,
        SinglePrioritizedServiceRegistryInterface::class,
        IdentityServiceRegistryInterface::class,
        ServiceRegistryInterface::class,
    ];

    public const REGISTRY_REQUIRED_ATTRIBUTE_MAP = [
        [
            [
                SinglePrioritizedServiceRegistryInterface::class,
                IdentityPrioritizedServiceRegistryInterface::class,
                IdentitySinglePrioritizedServiceRegistryInterface::class,
            ],
            PrioritizedServiceAttributeInterface::class,
        ],
        [
            [
                IdentityServiceRegistryInterface::class,
                IdentityPrioritizedServiceRegistryInterface::class,
                IdentitySinglePrioritizedServiceRegistryInterface::class,
            ],
            IdentityServiceAttributeInterface::class,
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
        private readonly string $targetClassAttribute,
        private readonly string $definitionId,
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

            if (!($reflector instanceof \ReflectionClass) || [] === $attributes) {
                continue;
            }

            if (null !== $this->interface && !$reflector->implementsInterface($this->interface)) {
                continue;
            }

            if (1 !== count($attributes)) {
                throw new \LogicException(
                    sprintf(
                        'Attribute #[%s] should be declared once only.',
                        $this->targetClassAttribute
                    )
                );
            }

            /** @var \ReflectionAttribute $classAttributeClass */
            $classAttributeClass = \array_pop($attributes);
            if ($classAttributeClass->getTarget() !== \Attribute::TARGET_CLASS) {
                throw new \LogicException(
                    sprintf(
                        'Attribute "#[%s]" target should be only "%s::TARGET_CLASS"',
                        $classAttributeClass->getName(),
                        \Attribute::class
                    )
                );
            }

            $classAttributeInstance = $classAttributeClass->newInstance();

            if (!($classAttributeInstance instanceof ServiceAttributeInterface)) {
                throw new \LogicException(
                    sprintf(
                        'Attribute #[%s] should implements "%s"',
                        $classAttributeClass->getName(),
                        ServiceAttributeInterface::class
                    )
                );
            }

            $registryDefinition = $container->getDefinition($this->definitionId);
            $this->processClass($reflector, $classAttributeInstance, $registryDefinition, $container, $definition, $attributes);
        }
    }

    protected function setupRegistryDefinition(ContainerBuilder $container): void
    {
        $definitionArgs = null === $this->interface ? [] : [$this->interface];

        if (!$container->hasDefinition($this->definitionId)) {
            $container->setDefinition($this->definitionId, new Definition($this->definitionClass, $definitionArgs));
        }
    }

    protected function accept(Definition $definition): bool
    {
        return $definition->isAutoconfigured() && !$definition->hasTag('container.ignore_attributes');
    }

    /** @noinspection NotOptimalIfConditionsInspection */
    protected function validateAttributeWithRegistryDefinition(object $attributeInstance): void
    {
        $registry = $this->getActualRegistryFromDefinition($this->definitionClass);

        foreach (self::REGISTRY_REQUIRED_ATTRIBUTE_MAP as [$registries, $requiredAttributeInterface]) {
            $attributeIsCorrect = $attributeInstance instanceof $requiredAttributeInterface;
            if (in_array($registry, $registries, true) && !$attributeIsCorrect) {
                throw new \LogicException(
                    \sprintf(
                        'Attribute #[%s] should implements "%s"',
                        get_class($attributeInstance),
                        $requiredAttributeInterface,
                    )
                );
            }
        }
    }

    protected function getActualRegistryFromDefinition(string $definitionClass): string
    {
        foreach (self::DEFAULT_REGISTRIES as $registry) {
            if (is_a($definitionClass, $registry, true)) {
                return $registry;
            }
        }

        throw new \LogicException(
            \sprintf(
                'Class "%s::class" does not implement any available registry type: [%s]',
                $definitionClass,
                implode(', ', self::DEFAULT_REGISTRIES)
            )
        );
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    abstract protected function processClass(
        \ReflectionClass $reflector,
        object $classAttributeInstance,
        Definition $registryDefinition,
        ContainerBuilder $container,
        Definition $definition,
        array $attributes
    ): void;
}