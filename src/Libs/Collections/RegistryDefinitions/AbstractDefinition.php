<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;
use TrayDigita\WP\Stack\Source\Exceptions\RegistryUnresolvableParameterException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryDefinitionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function get_class;
use function implode;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function reset;
use function sprintf;

/**
 * @template-implements RegistryDefinitionInterface<string, mixed>
 */
abstract class AbstractDefinition implements RegistryDefinitionInterface
{
    /**
     * @var bool $resolved indicates whether the definition has been resolved or not
     */
    protected bool $resolved;

    /**
     * @var mixed $value the definition of the value to be resolved
     */
    protected mixed $value;

    /**
     * @inheritdoc
     */
    public function value(RegistryInterface $registry, bool $autoResolveParameters = true): mixed
    {
        return $this->value??null;
    }

    /**
     * @inheritdoc
     */
    public function rawValue(): mixed
    {
        return $this->value??null;
    }

    /**
     * @inheritdoc
     */
    public function isResolved(): bool
    {
        return $this->resolved??false;
    }

    /**
     * Defines the parameters to be passed to the callable when resolving the definition
     *
     * @param array<array-key, mixed> $parameters Parameters to be passed to the callable when resolving the definition
     * @param ReflectionFunction|ReflectionMethod $reflector
     * @param RegistryInterface $registry
     * @param bool $autoResolveParameterName
     * @return array<array-key, mixed> The parameters to be passed to the callable when resolving the definition
     * @throws ExceptionInterface
     */
    protected function defineParameter(
        array $parameters,
        ReflectionFunction|ReflectionMethod $reflector,
        RegistryInterface $registry,
        bool $autoResolveParameterName
    ): array {
        if ($reflector->getNumberOfParameters() === 0) {
            return [];
        }
        $closureParameters = [];
        $unresolvedRequiredParameters = [];
        foreach ($reflector->getParameters() as $offset => $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $parameters)) {
                $closureParameters[$name] = $parameters[$name];
                continue;
            }
            if (array_key_exists($offset, $parameters)) {
                $closureParameters[$name] = $parameters[$offset];
                continue;
            }
            // maybe default value? // #skip
            if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() !== null) {
                $closureParameters[$name] = $parameter->getDefaultValue();
                continue;
            }
            $parameterType = $parameter->getType();
            $parameterTypes = [];
            if ($parameterType) {
                if ($parameterType instanceof ReflectionNamedType) {
                    $parameterTypes[] = $parameterType;
                } elseif ($parameterType instanceof ReflectionUnionType) {
                    $parameterTypes = array_filter($parameterType->getTypes(), function ($type) {
                        return $type instanceof ReflectionNamedType;
                    });
                }
                $is_optional = $parameter->isOptional();
                foreach ($parameterTypes as $type) {
                    if (!$type->isBuiltin()) {
                        $className = $type->getName();
                        if ($registry->has($className)) {
                            try {
                                $closureParameters[$name] = $registry->get($className);
                                continue 2;
                            } catch (ExceptionInterface $e) {
                                if (!$is_optional) {
                                    throw new RegistryUnresolvableParameterException(
                                        sprintf(
                                            'Cannot resolve required parameter: %s',
                                            $name
                                        ),
                                        0,
                                        $e
                                    );
                                }
                            }
                        }
                        if ($registry->isAutoWire()
                            && ($ref = Reflections::class($className)) && $ref->isInstantiable()
                        ) {
                            try {
                                $closureParameters[$name] = $registry->get($className);
                                continue 2;
                            } catch (ExceptionInterface $e) {
                                if (!$is_optional) {
                                    throw new RegistryUnresolvableParameterException(
                                        sprintf(
                                            'Cannot resolve required parameter: %s',
                                            $name
                                        ),
                                        0,
                                        $e
                                    );
                                }
                            }
                        } elseif (is_a($className, RegistryInterface::class, true)) {
                            $closureParameters[$name] = $registry;
                            continue 2;
                        }
                    }
                    /*
                    $className = $parameterType->getName();
                    if ($className === 'array') {
                        $closureParameters[$name] = [];
                    } elseif ($className === 'string') {
                        $closureParameters[$name] = '';
                    } elseif ($className === 'int') {
                        $closureParameters[$name] = 0;
                    } elseif ($className === 'float') {
                        $closureParameters[$name] = 0.0;
                    } elseif ($className === 'bool') {
                        $closureParameters[$name] = false;
                    }*/
                }
            }
            if ($parameter->isDefaultValueAvailable()) {
                $closureParameters[$name] = $parameter->getDefaultValue();
                continue;
            }
            if ($parameter->isOptional()) {
                continue;
            }
            if ($parameter->allowsNull()) {
                $closureParameters[$name] = null;
                continue;
            }
            if ($autoResolveParameterName) {
                try {
                    $val = $registry->get($name);
                    foreach ($parameterTypes as $type) {
                        if ($type->isBuiltin()) {
                            $typeName = $type->getName();
                            $valType = gettype($val);
                            if ($valType === $typeName) {
                                $closureParameters[$name] = $val;
                                continue 2;
                            }
                        } elseif (is_object($val)) {
                            $className = get_class($val);
                            if (is_a($className, $type->getName(), true)) {
                                $closureParameters[$name] = $val;
                                continue 2;
                            }
                        }
                    }
                } catch (Throwable) {
                }
            }
            $unresolvedRequiredParameters[$name] = $parameter;
        }
        if (empty($closureParameters) && count($unresolvedRequiredParameters) === 1) {
            $closureParameter = reset($unresolvedRequiredParameters);
            $key = $closureParameter->getName();
            if (!$closureParameter->getType()) {
                $unresolvedRequiredParameters = [];
                $closureParameters[$key] = $registry; // default to registry if no type is defined
            }
        }
        if (!empty($unresolvedRequiredParameters)) {
            $unresolvedKeys = implode(', ', array_keys($unresolvedRequiredParameters));
            throw new RegistryUnresolvableParameterException(
                sprintf(
                    'Cannot resolve required parameter: %s in callable %s',
                    $unresolvedKeys,
                    is_string($this->value) ? $this->value : (
                    is_array($this->value)
                        ? implode(
                            '::',
                            array_map(fn($v) => is_object($v) ? get_class($v) : (string)$v, $this->value)
                        ) : (
                    Is::closure($this->value) ? 'closure' : (
                    is_object($this->value)
                        ? get_class($this->value)
                        : (string)$this->value
                        )
                    )
                    )
                )
            );
        }
        foreach ($closureParameters as $key => $value) {
            if ($value instanceof RegistryDefinitionInterface) {
                $closureParameters[$key] = $value->value($registry, $autoResolveParameterName);
            }
        }
        return $closureParameters;
    }

    /**
     * @inheritdoc
     */
    public function resolveChain(string $id, RegistryInterface $registry) : ?RegistryChainingIdentity
    {
        try {
            $raw = $registry->getRaw($id);
            if ($raw instanceof Value) {
                $value = $raw->rawValue();
            } elseif ($raw instanceof RegistryDefinitionInterface) {
                if (!$raw->isResolved()) {
                    $raw = $raw->rawValue();
                    if (is_string($raw) && ($ref = Reflections::class($raw))) {
                        if (!$ref->implementsInterface(RegistryComponentInterface::class)) {
                            return null;
                        }
                        return $ref->newInstanceWithoutConstructor()->registerSelf($id, $registry);
                    }
                    return $raw instanceof RegistryComponentInterface ? $raw->registerSelf($id, $registry) : null;
                } else {
                    $value = $raw->value($registry);
                }
            } elseif (is_object($raw)) {
                $value = $raw;
            } elseif (is_string($raw)) {
                $ref = Reflections::class($raw);
                if (!$ref || !$ref->implementsInterface(RegistryComponentInterface::class)) {
                    return null;
                }
                return $ref->newInstanceWithoutConstructor()->registerSelf($id, $registry);
            } else {
                return null;
            }
            if (!$value instanceof RegistryComponentInterface) {
                return null;
            }
            return $value->registerSelf($id, $registry);
        } catch (Throwable) {
            return null;
        }
    }
}
