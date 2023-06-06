<?php

declare(strict_types=1);

namespace Highcore\Registry\Symfony\Resolver;

final class FirstParameterCallableIdentifierResolver implements CallableIdentifierResolver
{
    public function resolve(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): string {
        if (1 !== $method->getNumberOfRequiredParameters()) {
            throw new \LogicException(\sprintf(
                'Handler method "%s::%s" should have only one argument, argument of command',
                $class->getName(), $method->getName()
            ));
        }

        $parameter = $method->getParameters()[0];

        if (!$parameter->hasType()
            || $parameter->getType() instanceof \ReflectionUnionType
            || $parameter->getType()->isBuiltin()
            || (new \ReflectionClass($parameter->getType()->getName()))->isInterface()
        ) {
            throw new \LogicException(\sprintf(
                'First parameter of handler method "%s::%s" should be of Command class.',
                $class->getName(), $method->getName()
            ));
        }

        return $parameter->getName();
    }
}