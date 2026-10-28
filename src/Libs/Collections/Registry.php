<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections;

use ReflectionClass;
use Throwable;
use TrayDigita\WP\Stack\Source\Exceptions\CyclicRedundancyException;
use TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException;
use TrayDigita\WP\Stack\Source\Exceptions\LockedException;
use TrayDigita\WP\Stack\Source\Exceptions\RegistryLockedException;
use TrayDigita\WP\Stack\Source\Exceptions\RegistryNotFoundException;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryDefinitionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryNoChainingInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\SingletonInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\IllegalOperationExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryLockedExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryNotFoundExceptionInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\AutoWire;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Factory;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Reference;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\ReferenceNoChaining;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Value;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\ValueNoChaining;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function class_exists;
use function in_array;
use function is_a;
use function is_object;
use function is_string;
use function max;
use function min;
use function sprintf;

class Registry implements RegistryInterface
{
    /**
     * @var bool $autoWire Whether to automatically wire dependencies when getting a value from the registry
     */
    public readonly bool $autoWire;

    /**
     * @var bool $autoResolveParameter Whether to automatically resolve
     * parameters when getting a value from the registry
     */
    public readonly bool $autoResolveParameter;

    /**
     * @var int<1, 256> $cyclicDepth The maximum depth of cyclic configuration
     */
    private int $cyclicDepth;

    /**
     * @var string[]|null $chainKeys The keys of the registry that are currently being chained
     */
    private ?array $chainKeys = null;

    /**
     * @var string|null $currentSet The key of the registry that is currently being set
     */
    private ?string $currentSet = null;

    /**
     * @var array<string, int<0, max>> $configuring The keys of the registry
     * Integer 1 if the keys configuring, 0 if the keys not configuring
     */
    private array $configuring = [];

    /**
     * @var array<string, mixed> $registry The registry storage
     */
    private array $registry;

    /**
     * @var array<string, bool> $registryKeys The keys of the registry
     * Boolean true if the keys locked, false if the keys unlocked
     */
    private array $registryKeys;

    /**
     * @var array<string, string> $references The references of the registry
     */
    private array $references;

    /**
     * @var array<string, bool> $unlockableKeys The keys of the registry
     * Boolean true if the keys unlockable, false if the keys not unlockable
     */
    private array $unlockableKeys = [];

    /**
     * @var array<string, bool> $configured The keys of the registry
     * Boolean true if the keys configured, false if the keys not configured
     */
    private array $configured = [];

    /**
     * Constructor
     *
     * @param array<string, mixed> $definitions The initial definitions for the registry
     * @param bool $autoWire
     * @param bool $autoResolveParameter
     * @param bool $lockAll
     */
    public function __construct(
        array $definitions = [],
        bool $autoWire = true,
        bool $autoResolveParameter = true,
        bool $lockAll = false
    ) {
        $this->autoWire = $autoWire;
        $this->autoResolveParameter = $autoResolveParameter;
        $this->cyclicDepth = self::CYCLIC_DEPTH;
        if (isset($this->registryKeys)) {
            throw new RuntimeException(
                'Registry is already initialized and cannot be re-initialized'
            );
        }
        foreach ($definitions as $key => $value) {
            try {
                $this->set($key, $value, $lockAll, !$lockAll);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * @inheritdoc
     * @return void
     */
    public function setCyclicDepth(int $cyclicDepth): void
    {
        $this->cyclicDepth = min(self::MAX_CYCLIC_DEPTH, max(self::MIN_CYCLIC_DEPTH, $cyclicDepth));
    }

    /**
     * @inheritdoc
     */
    public function getCyclicDepth(): int
    {
        return $this->cyclicDepth;
    }

    /**
     * @inheritdoc
     */
    public function isAutoWire(): bool
    {
        return $this->autoWire;
    }

    /**
     * @inheritdoc
     */
    public function configureCount(string $name): int
    {
        return $this->configuring[$name] ?? 0;
    }

    /**
     * @inheritdoc
     * @param string $id The key to set
     * @param mixed $value The value to set
     * @param bool $lock Whether to lock the key after setting
     * @param bool $unlockable Whether the key can be unlocked after setting
     * @return self
     */
    public function set(
        string $id,
        mixed $value,
        bool $lock = false,
        bool $unlockable = true
    ): self {
        if (!empty($this->registryKeys[$id])) {
            throw new RegistryLockedException(
                sprintf(
                    'Registry "%1$s" is locked and cannot be set',
                    $id
                )
            );
        }
        if (isset($this->configuring[$id])) {
            throw new CyclicRedundancyException(
                sprintf(
                    'Registry "%1$s" is cyclically configured and cannot be set',
                    $id
                )
            );
        }
        if (isset($this->configured[$id])) {
            throw new IllegalOperationException(
                sprintf(
                    'Registry "%1$s" is already configured and cannot be set',
                    $id
                )
            );
        }

        /**
         * @see RegistryChainingIdentity::register()
         * The chain operation is only allowed for ValueNoChaining,
         * which is a special case of RegistryDefinitionInterface that does not allow chaining.
         * This prevents infinite loops and ensures that the registry is not modified during a chain operation.
         */
        if (!empty($this->chainKeys)
            && (!$value instanceof ValueNoChaining && !$value instanceof ReferenceNoChaining)
        ) {
            throw new IllegalOperationException(
                sprintf(
                    'Registry "%1$s" is currently doing a chain operation and cannot be set',
                    $id
                )
            );
        }
        if (Is::closure($value)) {
            $value = Factory::create($value);
        }
        $this->registry[$id] = $value;
        $this->registryKeys[$id] = $lock;
        $this->unlockableKeys[$id] = $unlockable;
        $this->currentSet = $id;
        unset($this->configured[$id]);
        // call chaining if the value is a RegistryDefinitionInterface
        if (!$value instanceof RegistryNoChainingInterface
            && (
                $value instanceof RegistryDefinitionInterface
                || $value instanceof RegistryComponentInterface
            )
        ) {
            $chains = $value instanceof RegistryComponentInterface
                ? $value->registerSelf($id, $this)
                : $value->resolveChain($id, $this);
            if ($chains) {
                try {
                    $chainsKeys = ($this->chainKeys??null);
                    $this->chainKeys = $chains->getIdentities();
                    $chains->register($this);
                } finally {
                    $this->chainKeys = $chainsKeys;
                }
            }
        }
        $this->currentSet = $id; // set again
        return $this;
    }

    /**
     * @inheritdoc
     *
     * @template T of object
     * @param class-string<T>|string $id The key to get
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (isset($this->configured[$id])) {
            if (!isset($this->registry[$id])) {
                throw new RegistryNotFoundException(
                    sprintf(
                        'Registry "%1$s" is not found or not registered',
                        $id
                    )
                );
            }
            return $this->registry[$id];
        }

        if (!isset($this->registryKeys[$id])) {
            if ($this->autoWire && Is::validClassName($id)
                && ($ref = Reflections::class($id)) && $ref->isInstantiable()
            ) {
                try {
                    $value = AutoWire::create($id)->value($this);
                    $this->set($id, Value::create($value));
                    return $this->get($id);
                } catch (Throwable) {
                }
            }
            throw new RegistryNotFoundException(
                sprintf(
                    'Registry "%1$s" is not found or not registered',
                    $id
                )
            );
        }
        $this->configuring[$id] ??= 0;
        if ($this->configuring[$id]++ >= $this->getCyclicDepth()) {
            unset($this->configuring[$id]);
            throw new CyclicRedundancyException(
                sprintf(
                    'Registry "%1$s" is cyclically configured after %2$d iterations',
                    $id,
                    $this->getCyclicDepth()
                )
            );
        }
        if (isset($this->references[$id])) {
            $ref = $this->references[$id];
            if ($ref === $id) {
                unset($this->configuring[$id]);
                throw new CyclicRedundancyException(
                    sprintf(
                        'Registry "%1$s" is cyclically configured with same reference',
                        $id
                    )
                );
            }
            if (!isset($this->registry[$ref])) {
                unset($this->configuring[$id]);
                throw new RegistryNotFoundException(
                    sprintf(
                        'Registry "%1$s" is not found or not registered',
                        $ref
                    )
                );
            }
            try {
                $result = $this->get($ref);
                unset($this->configuring[$id]);
                return $result;
            } catch (Throwable) {
                unset($this->configuring[$id]);
                throw new RegistryNotFoundException(
                    sprintf(
                        'Registry "%1$s" is not found or not registered',
                        $ref
                    )
                );
            }
        }
        try {
            $result = $this->registry[$id];
            if ((is_string($result) || is_object($result))
                && is_a($result, SingletonInterface::class, true)
            ) {
                $result = $result::getInstance();
            } elseif ($result instanceof Reference) {
                if ($result->getReferenceName() === $id) {
                    unset($this->configuring[$id]);
                    throw new CyclicRedundancyException(
                        sprintf(
                            'Registry "%1$s" is cyclically with same reference',
                            $id
                        )
                    );
                }
                $refName = $result->getReferenceName();
                $result = $result->value($this, $this->autoResolveParameter);
                $this->references[$id] = $refName;
                unset($this->configuring[$id], $this->registry[$id]);
                return $result;
            } elseif ($result instanceof RegistryDefinitionInterface) {
                $result = $result->value($this, $this->autoResolveParameter);
            } elseif (Is::closure($result)) {
                $result = $result($this);
            }
            unset($this->configuring[$id]);
            $this->configured[$id] = true;
            return $this->registry[$id] = $result;
        } finally {
            unset($this->configuring[$id]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getRaw(string $id): mixed
    {
        if (!isset($this->registry[$id])) {
            throw new RegistryNotFoundException(
                sprintf(
                    'Registry "%1$s" is not found or not registered',
                    $id
                )
            );
        }
        return $this->registry[$id];
    }

    /**
     * @inheritdoc
     */
    public function remove(string $id): void
    {
        if (!$this->has($id)) {
            throw new RegistryNotFoundException(
                sprintf(
                    'Registry "%1$s" is not found or not registered',
                    $id
                )
            );
        }
        if ($this->configureCount($id) > 0) {
            throw new IllegalOperationException(
                sprintf(
                    'Registry "%1$s" is currently being configured and cannot be removed',
                    $id
                )
            );
        }
        if ($this->locked($id)) {
            throw new RegistryLockedException(
                sprintf(
                    'Registry "%1$s" is locked and cannot be removed',
                    $id
                )
            );
        }
        if (!empty($this->chainKeys) && in_array($id, $this->chainKeys, true)) {
            throw new IllegalOperationException(
                sprintf(
                    'Registry "%1$s" is currently doing a chain operation and cannot be removed',
                    $id
                )
            );
        }
        if ($id === $this->currentSet) {
            $this->currentSet = null;
        }
        $refIds = [];
        $locked = false;
        foreach ($this->references??[] as $key => $ref) {
            if ($ref === $id) {
                $refIds[] = $key;
                if (!$locked && $this->locked($key)) {
                    $locked = true;
                }
            }
        }
        if ($locked) {
            throw new RegistryLockedException(
                sprintf(
                    'Registry "%1$s" is referenced by other locked registries and cannot be removed',
                    $id
                )
            );
        }
        unset($this->registry[$id]);
        unset($this->registryKeys[$id]);
        unset($this->unlockableKeys[$id]);
        unset($this->configured[$id]);
        foreach ($refIds as $refId) {
            unset($this->references[$refId]);
        }
    }

    /**
     * @inheritdoc
     */
    public function has(string $id): bool
    {
        return isset($this->registryKeys[$id]);
    }

    /**
     * @inheritdoc
     */
    public function lock(?string $id = null, bool $unlockable = true): void
    {
        $id ??= $this->currentSet;
        if ($id === null) {
            return;
        }
        if (isset($this->registryKeys[$id])) {
            $this->registryKeys[$id] = true;
            if (!$unlockable) {
                $this->unlockableKeys[$id] = false;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function unlock(string $id): void
    {
        if (!isset($this->registryKeys[$id])) {
            return;
        }
        if (!$this->unlockable($id)) {
            throw new LockedException(
                sprintf(
                    'Registry "%1$s" is not unlockable and cannot be unlocked',
                    $id
                )
            );
        }
        $this->registryKeys[$id] = false;
        $this->unlockableKeys[$id] = true;
    }

    /**
     * @inheritdoc
     */
    public function unlockable(string $id): bool
    {
        return !empty($this->unlockableKeys[$id]);
    }

    /**
     * @inheritdoc
     */
    public function locked(string $id): bool
    {
        return !empty($this->registryKeys[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param mixed ...$args
     * @return T
     * @throws RegistryNotFoundExceptionInterface
     * @throws ExceptionInterface
     * @throws IllegalOperationExceptionInterface
     * @throws RegistryLockedExceptionInterface
     */
    public function make(string $className, ...$args): object
    {
        if (Is::validClassName($className) && class_exists($className)) {
            $ref = new ReflectionClass($className);
            $refName = $ref->getName();
            try {
                if ($this->has($refName)) {
                    return $this->get($refName);
                }
            } catch (RegistryNotFoundException
            |RegistryNotFoundExceptionInterface
            |IllegalOperationExceptionInterface
            |RegistryLockedExceptionInterface
            |ExceptionInterface) {
                // ignore
            }
            $this->set($refName, AutoWire::create($refName)->constructorParameters($args));
            return $this->get($refName);
        }
        throw new RuntimeException(
            sprintf(
                'Invalid class name %s',
                $className
            )
        );
    }

    /**
     * Magic method to get a value from the registry using property access.
     *
     * @template T of object
     * @param class-string<T>|string $name The key to get
     * @return ($name is class-string<T> ? T : mixed)
     * @throws RegistryNotFoundExceptionInterface
     * @throws ExceptionInterface
     * @throws IllegalOperationExceptionInterface
     * @throws RegistryLockedExceptionInterface
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic method to set a value in the registry using property access.
     *
     * @param string $name The key to set
     * @param mixed $value The value to set
     * @return void
     * @throws RegistryLockedExceptionInterface
     * @throws IllegalOperationExceptionInterface
     * @throws ExceptionInterface
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Magic method to check if a key exists in the registry using property access.
     *
     * @param string $name The key to check
     * @return bool True if the key exists, false otherwise
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Magic method to remove a key from the registry using property access.
     *
     * @param string $name The key to remove
     * @return void
     * @throws RegistryNotFoundExceptionInterface
     * @throws IllegalOperationExceptionInterface
     * @throws RegistryLockedExceptionInterface
     * @throws ExceptionInterface
     */
    public function __unset(string $name): void
    {
        $this->remove($name);
    }
}
