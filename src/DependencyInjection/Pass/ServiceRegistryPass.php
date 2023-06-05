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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceRegistryPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $definition,
        private readonly string $attributeName = 'code'
    ) {}

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->definition)) {
            return;
        }

        $registryDefinition = $container->getDefinition($this->definition);
        $ids = $container->findTaggedServiceIds($this->definition);

        foreach ($ids as $className => $attributes) {
            $filtered = \array_filter($attributes, fn (array $item): bool
                => isset($item[$this->attributeName]));

            if (0 === \count($filtered)) {
                continue;
            }

            $registryDefinition->addMethodCall('register', [
                $filtered[0][$this->attributeName],
                new Reference($className)]
            );
        }
    }
}
