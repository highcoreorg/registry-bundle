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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ReferenceRegistryPass implements CompilerPassInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private string $definition;

    public function __construct(string $definition)
    {
        $this->definition = $definition;
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->definition)) {
            return;
        }

        $registryDefinition = $container->getDefinition($this->definition);
        $ids = $container->findTaggedServiceIds($this->definition);

        foreach ($ids as $className => $attributes) {
            $registryDefinition->addMethodCall('register', [
                $className,
                new Reference($className)
            ]);
        }
    }
}
