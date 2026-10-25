<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use function array_shift;
use function call_user_func_array;
use function class_exists;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;
use function str_contains;

class Factory extends AbstractDefinition
{
    /**
     * @var array<string, mixed> $parameters The parameters to be passed to the callable when resolving the definition
     */
    protected array $parameters;

    /**
     * @var ExceptionInterface $failingException The exception that caused the definition to fail
     */
    private ExceptionInterface $failingException;

    /**
     * @param array<string|object|class-string>|string|callable $value
     * The callable to be used for resolving the definition
     */
    final public function __construct(array|string|callable $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a new instance of the FactoryDefinition class
     *
     * @param array<string|object|class-string>|string|callable $value
     * The callable to be used for resolving the definition
     * @return self
     */
    public static function create(array|string|callable $value): self
    {
        return new static($value);
    }

    /**
     * Removes a parameter from the definition
     *
     * @param string $key The key of the parameter to be removed
     * @return self
     */
    public function removeParameter(string $key): self
    {
        unset($this->parameters[$key]);
        return $this;
    }

    /**
     * @return array<string, mixed> The parameters to be passed to the callable when resolving the definition
     */
    public function getParameters(): array
    {
        return $this->parameters ?? [];
    }

    /**
     * Defines a parameter to be passed to the callable when resolving the definition
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function parameter(string $key, mixed $value): self
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    /**
     * @inheritdoc
     * @throws ExceptionInterface
     */
    public function value(RegistryInterface $registry, bool $autoResolveParameters = true): mixed
    {
        if ($this->isResolved()) {
            return $this->value ?? null;
        }
        if (isset($this->failingException)) {
            throw $this->failingException;
        }
        if (is_callable($this->value)) {
            try {
                $callable = $this->value;
                $reflection = is_array($callable)
                    ? new ReflectionMethod(...$callable)
                    : (
                    is_string($callable) || Is::closure($callable)
                        ? new ReflectionFunction($callable)
                        : null
                    );
                if (!$reflection) {
                    throw new RuntimeException(
                        sprintf(
                            'Registry value is not a callable, got %s',
                            gettype($this->value)
                        )
                    );
                }
                $parameters = $this->defineParameter(
                    $this->getParameters(),
                    $reflection,
                    $registry,
                    $autoResolveParameters
                );
                $this->value = call_user_func_array($this->value, $parameters);
            } catch (Throwable $e) {
                if (!$e instanceof ExceptionInterface) {
                    $e = new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                /**
                 * @var ExceptionInterface $e
                 */
                $this->failingException = $e;
                throw $this->failingException;
            }
        } elseif (is_array($this->value)
            || is_string($this->value) && Is::validClassName($this->value) && class_exists($this->value)
        ) {
            try {
                $value = is_string($this->value) ? [$this->value] : $this->value;
                $className = array_shift($value);
                $method = array_shift($value);
                if (!is_string($className)) {
                    throw new RuntimeException(
                        sprintf(
                            'Registry value is not a string or callable, got %s',
                            gettype($value)
                        )
                    );
                }
                if (class_exists($className)) {
                    $reflection = new ReflectionClass($className);
                    $refMethod = null;
                    if ($method && is_string($method)) {
                        if (!$reflection->hasMethod($method)) {
                            throw new RuntimeException(
                                sprintf('Class %s does not have method %s', $className, $method)
                            );
                        }
                        $refMethod = $reflection->getMethod($method);
                    }
                    if ($refMethod && !$refMethod->isPublic()) {
                        throw new RuntimeException(
                            sprintf('Class %s method %s is not public', $className, $method)
                        );
                    }
                    $object = null;
                    if ($reflection->implementsInterface(RegistryInterface::class)) {
                        $object = $registry;
                    } else {
                        if ($reflection->hasMethod('__construct')) {
                            $constructor = $reflection->getConstructor();
                            if ($constructor) {
                                if (!$constructor->isPublic()) {
                                    throw new RuntimeException(
                                        sprintf('Class %s constructor is not public', $className)
                                    );
                                }
                                $parameters = $this->defineParameter(
                                    $this->getParameters(),
                                    $constructor,
                                    $registry,
                                    $autoResolveParameters
                                );
                            } else {
                                $parameters = [];
                            }
                        } else {
                            $parameters = [];
                        }
                        if (!$refMethod?->isStatic()) {
                            $object = $reflection->newInstanceArgs($parameters);
                        }
                    }
                    if ($refMethod) {
                        $parameters = $this->defineParameter(
                            $this->getParameters(),
                            $refMethod,
                            $registry,
                            $autoResolveParameters
                        );
                        $callback = $refMethod->isStatic() || !isset($object)
                            ? sprintf('%s::%s', $reflection->getName(), $method)
                            : [$object, $method];
                        if (!is_callable($callback)) {
                            throw new RuntimeException('Generated callback is not callable.');
                        }
                        $this->value = call_user_func_array(
                            $callback,
                            $parameters
                        );
                    } else {
                        $this->value = $object;
                    }
                } elseif (!is_callable([$className, $method])) {
                    throw new RuntimeException(
                        sprintf(
                            'Registry value is not a callable, got %s',
                            gettype($value)
                        )
                    );
                } else {
                    $reflection = str_contains($className, '::')
                        ? new ReflectionMethod($className)
                        : new ReflectionFunction($className);
                    $parameters = $this->defineParameter(
                        $this->getParameters(),
                        $reflection,
                        $registry,
                        $autoResolveParameters
                    );
                    if (!is_callable($className)) {
                        throw new RuntimeException(
                            sprintf(
                                'Registry value is not a callable, got %s',
                                gettype($value)
                            )
                        );
                    }
                    $this->value = call_user_func_array($className, $parameters);
                }
            } catch (Throwable $e) {
                if (!$e instanceof ExceptionInterface) {
                    $e = new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                /**
                 * @var ExceptionInterface $e
                 */
                $this->failingException = $e;
                throw $this->failingException;
            }
        } else {
            $this->failingException = new RuntimeException(
                sprintf(
                    'Registry value is not a callable or array, got %s',
                    gettype($this->value)
                )
            );
            throw $this->failingException;
        }

        $this->resolved = true;
        return $this->value;
    }
}
