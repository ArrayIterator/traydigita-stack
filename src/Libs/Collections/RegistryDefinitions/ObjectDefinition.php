<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use ReflectionClass;
use Throwable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use function class_exists;
use function gettype;
use function is_string;
use function sprintf;

class ObjectDefinition extends AbstractDefinition
{
    /**
     * @var bool $resolved indicates whether the definition has been resolved or not
     */
    protected bool $resolved;

    /**
     * @var ExceptionInterface $failingException The exception that caused the definition to fail
     */
    private ExceptionInterface $failingException;

    /**
     * @var array<array-key, mixed> $constructorParameters
     * The parameters to be passed to the constructor when resolving the definition
     */
    private array $constructorParameters;

    /**
     * @var ?string $method The method to be called on the object when resolving the definition
     */
    private ?string $method;

    /**
     * @var ?array<string|int, mixed> $methodParameters
     * The parameters to be passed to the method when resolving the definition
     */
    private ?array $methodParameters;

    /**
     * @param string $className The class name to be used for resolving the definition
     */
    final public function __construct(string $className)
    {
        $this->value = $className;
    }

    /**
     * Creates a new instance of the ObjectDefinition class
     *
     * @param string $className The class name to be used for resolving the definition
     * @return static
     */
    public static function create(string $className): static
    {
        return new static($className);
    }

    /**
     * Sets the method to be called on the object when resolving the definition
     *
     * @param ?string $method The method to be called on the object when resolving the definition
     * @param array<array-key, mixed> $methodParameters
     *  The parameters to be passed to the method when resolving the definition
     * @return static
     */
    public function method(?string $method, array $methodParameters): static
    {
        if (!$method) {
            $this->method = null;
            $this->methodParameters = null;
            return $this;
        }
        $this->method = $method;
        $this->methodParameters = $methodParameters;
        return $this;
    }

    /**
     * @return ?string The method to be called on the object when resolving the definition
     */
    public function getMethod(): ?string
    {
        return $this->method??null;
    }

    /**
     * @return ?array<string|int, mixed> The parameters to be passed to the method when resolving the definition
     */
    public function getMethodParameters(): ?array
    {
        return $this->methodParameters??null;
    }

    /**
     * Sets the parameters to be passed to the constructor when resolving the definition
     *
     * @param array<array-key, mixed> $parameters
     * The parameters to be passed to the constructor when resolving the definition
     * @return $this
     */
    public function constructorParameters(array $parameters): self
    {
        $this->constructorParameters = $parameters;
        return $this;
    }

    /**
     * Sets a parameter to be passed to the constructor when resolving the definition
     *
     * @param string $key The key of the parameter to be set
     * @param mixed $value The value of the parameter to be set
     * @return $this
     */
    public function constructorParameter(string $key, mixed $value): self
    {
        $this->constructorParameters ??= [];
        $this->constructorParameters[$key] = $value;
        return $this;
    }

    /**
     * @return array<array-key, mixed> The parameters to be passed to the constructor when resolving the definition
     */
    public function getConstructorParameters(): array
    {
        return $this->constructorParameters??[];
    }

    /**
     * Removes a parameter from the definition
     *
     * @param string $key The key of the parameter to be removed
     * @return $this
     */
    public function removeConstructorParameter(string $key): self
    {
        if (!isset($this->constructorParameters)) {
            return $this;
        }
        unset($this->constructorParameters[$key]);
        return $this;
    }

    /**
     * @throws ExceptionInterface
     */
    public function value(RegistryInterface $registry, bool $autoResolveParameters = true): mixed
    {
        if (!empty($this->failingException)) {
            throw $this->failingException;
        }
        if ($this->isResolved()) {
            return $this->value ?? null;
        }
        if (!is_string($this->value)) {
            $this->failingException = new RuntimeException(
                sprintf(
                    'Invalid class name %s',
                    gettype($this->value)
                )
            );
            throw $this->failingException;
        }
        if (!class_exists($this->value)) {
            $this->failingException = new RuntimeException(
                sprintf('Class %s does not exist', $this->value)
            );
            throw $this->failingException;
        }
        try {
            $reflection = new ReflectionClass($this->value);
            if ($reflection->implementsInterface(RegistryInterface::class)) {
                $object = $registry;
            } else {
                if (($this->method??null) && !$reflection->hasMethod($this->method)) {
                    $this->failingException = new RuntimeException(
                        sprintf('Class %s does not have method %s', $this->value, $this->method)
                    );
                    throw $this->failingException;
                }
                if ($reflection->hasMethod('__construct')) {
                    $constructor = $reflection->getMethod('__construct');
                    if (!$constructor->isPublic()) {
                        $this->failingException = new RuntimeException(
                            sprintf('Class %s constructor is not public', $this->value)
                        );
                        throw $this->failingException;
                    }
                    $parameters = $this->defineParameter(
                        $this->getConstructorParameters(),
                        $constructor,
                        $registry,
                        $autoResolveParameters
                    );
                } else {
                    $parameters = [];
                }
                $object = new $this->value(...$parameters);
            }
            if ($this->method??null) {
                $methodParameters = $this->defineParameter(
                    $this->getMethodParameters() ?? [],
                    $reflection->getMethod($this->method),
                    $registry,
                    $autoResolveParameters
                );
                $this->value = $object->{$this->method}(...$methodParameters);
            } else {
                $this->value = $object;
            }
            $this->resolved = true;
            return $this->value;
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
    }
}
