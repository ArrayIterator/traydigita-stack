<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\IconSets\Entypo;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\IconSets\Octicons;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\IconSets\Orbiters;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\WebComponent;
use function class_exists;
use function is_object;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * @template-implements IteratorAggregate<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>>
 * @template-implements ArrayAccess<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>>
 */
class Collections implements JsonSerializable, Countable, IteratorAggregate, ArrayAccess
{
    /**
     * @var array<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>> $components
     */
    private array $components = [];

    /**
     * @var array<non-empty-lowercase-string, class-string<WebComponent<non-empty-lowercase-string>>> $lazyComponents
     */
    private array $lazyComponents = [
        Octicons::ID => Octicons::class,
        Entypo::ID => Entypo::class,
        Orbiters::ID => Orbiters::class,
    ];

    /**
     * @return array<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>>
     */
    public function getComponents(): array
    {
        foreach ($this->lazyComponents as $id => $constructor) {
            if (!isset($this->components[$id])) {
                $component = new $constructor();
                unset($this->lazyComponents[$id]);
                $this->components[$id] = $component;
            }
        }
        return $this->components;
    }

    /**
     * @param WebComponent<non-empty-lowercase-string> $component
     * @return void
     */
    public function add(WebComponent $component): void
    {
        $this->components[$component::id()] = $component;
    }

    /**
     * @template Y of WebComponent<non-empty-lowercase-string>
     * @param string|Y|class-string<Y> $id
     * @return non-empty-lowercase-string
     */
    private function detectId(string|WebComponent $id): string
    {
        if (is_string($id)
            && Is::validClassName($id)
            && class_exists($id)) {
            $ref = Reflections::class($id);
            if ($ref
                && $ref->isSubclassOf(WebComponent::class)
                && $ref->isInstantiable()
                && $ref->getConstructor()->isPublic()
                && $ref->hasMethod('id')
                && $ref->getMethod('id')->isStatic()
            ) {
                /**
                 * @var class-string<WebComponent<non-empty-lowercase-string>> $className
                 */
                $className = $ref->getName();
                $id = $className::id();
            }
        }
        if (is_object($id)) {
            return $id->id();
        }
        $id = strtolower($id);
        if ($id === '') {
            throw new InvalidArgumentException('Invalid component ID');
        }
        return $id;
    }

    /**
     * Add a lazy-loaded component
     *
     * @param class-string<WebComponent<non-empty-lowercase-string>> $constructor
     * @return void
     * @throws InvalidArgumentException
     */
    public function addLazy(string $constructor): void
    {
        $ref = Reflections::class($constructor);
        if (!$ref || !$ref->isSubclassOf(WebComponent::class)) {
            throw new InvalidArgumentException(sprintf(
                'The class %s must be a subclass of %s',
                $constructor,
                WebComponent::class
            ));
        }
        $constructorRef = $ref->getConstructor();
        if (!$constructorRef || !$constructorRef->isPublic()) {
            throw new InvalidArgumentException(sprintf(
                'The constructor of %s must be public',
                $constructor
            ));
        }
        if ($constructorRef->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(sprintf(
                'The constructor of %s must not require any parameters',
                $constructor
            ));
        }
        if ($ref->isInstantiable()) {
            /**
             * @var class-string<WebComponent<non-empty-lowercase-string>> $className
             */
            $className = $ref->getName();
            $id = $className::id();
            /**
             * @var class-string<WebComponent<non-empty-lowercase-string>> $constructor
             */
            $this->lazyComponents[$id] = $className;
        } else {
            throw new InvalidArgumentException(sprintf(
                'The class %s is not instantiable',
                $constructor
            ));
        }
    }

    /**
     * @template Y of WebComponent<non-empty-lowercase-string>
     * @param string|Y|class-string<Y> $id
     * @return bool
     */
    public function has(string|WebComponent $id): bool
    {
        $id = $this->detectId($id);
        return isset($this->components[$id]) || isset($this->lazyComponents[$id]);
    }

    /**
     * @template Y of WebComponent<non-empty-lowercase-string>
     * @param string|Y|class-string<Y> $id
     * @return ?Y
     */
    public function get(string|WebComponent $id): ?WebComponent
    {
        $id = $this->detectId($id);
        if (isset($this->components[$id])) {
            if (isset($this->lazyComponents[$id])) {
                unset($this->lazyComponents[$id]);
            }
            /**
             * @var Y $res
             */
            $res = $this->components[$id];
            return $res;
        }
        if (isset($this->lazyComponents[$id])) {
            $constructor = $this->lazyComponents[$id];
            $component = new $constructor();
            unset($this->lazyComponents[$id]);
            $this->components[$id] = $component;
            /**
             * @var Y $component
             */
            return $component;
        }
        return null;
    }

    /**
     * @template Y of WebComponent<non-empty-lowercase-string>
     * @param string|Y|class-string<Y> $id
     * @return bool
     */
    public function remove(string|WebComponent $id): bool
    {
        $id = $this->detectId($id);
        if (isset($this->components[$id])) {
            unset($this->components[$id]);
            return true;
        }
        if (isset($this->lazyComponents[$id])) {
            unset($this->lazyComponents[$id]);
            return true;
        }
        return false;
    }

    /**
     * @return array<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>>
     */
    public function jsonSerialize(): array
    {
        return $this->components;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->components);
    }

    /**
     * @return ArrayIterator<non-empty-lowercase-string, WebComponent<non-empty-lowercase-string>>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->components); // @phpstan-ignore-line
    }

    /**
     * Magic method to handle dynamic method calls.
     * @param string $name
     * @return WebComponent<non-empty-lowercase-string>|null
     */
    public function __get(string $name): ?WebComponent
    {
        return $this->get($name);
    }

    /**
     * Magic method to handle dynamic property access.
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if ($value instanceof WebComponent) {
            $this->add($value);
        }
    }

    /**
     * Magic method to handle dynamic property unsetting.
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        $this->remove($name);
    }

    /**
     * Magic method to check if a property is set.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return WebComponent<non-empty-lowercase-string>|null
     */
    public function offsetGet(mixed $offset): ?WebComponent
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset) || !$value instanceof WebComponent) {
            return;
        }
        $this->add($value);
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->remove($offset);
        }
    }
}
