<?php

declare(strict_types=1);

namespace Highcore\Registry\Bundle\Resolver;

final class FirstParameterCallableIdentifierResolver implements CallableIdentifierResolver
{
    public function __construct(private readonly bool $allowInterface = true)
    {
    }

    public function resolve(
        \ReflectionClass $class,
        \ReflectionMethod $method,
        object $methodAttribute,
        object $classAttribute
    ): string {
        $parameter = $method->getParameters()[0];

        if (!$parameter->hasType()
            || $parameter->getType() instanceof \ReflectionUnionType
            || $parameter->getType()->isBuiltin()
        ) {
            throw new \LogicException(\sprintf(
                'First parameter of the "%s::%s" method must be a single class type.',
                $class->getName(),
                $method->getName()
            ));
        }

        if (!$this->allowInterface && (new \ReflectionClass($parameter->getType()->getName()))->isInterface()) {
            throw new \LogicException(\sprintf(
                'First parameter of the "%s::%s" should be an class, interface type does not allowed.',
                $class->getName(),
                $method->getName()
            ));
        }

        return $parameter->getName();
    }
}
