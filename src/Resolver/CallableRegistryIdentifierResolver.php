<?php

declare(strict_types=1);

namespace Highcore\Registry\Symfony\Resolver;

interface CallableRegistryIdentifierResolver
{
    public function resolve(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): string;
}