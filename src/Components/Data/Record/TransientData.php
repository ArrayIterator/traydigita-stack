<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Data\Record;

use SensitiveParameter;
use Serializable;
use TrayDigita\WP\Stack\Source\Interfaces\Data\TransientDataInterface;
use function serialize;
use function time;
use function unserialize;

/**
 * @template T of mixed
 * @property-read  string $name
 * @property-read T $value
 * @property-read int $expiration
 * @property-read bool $expired
 * @template-implements TransientDataInterface<T>
 */
final class TransientData implements Serializable, TransientDataInterface
{
    /**
     * @var bool $expired Indicates whether the transient data has expired
     */
    protected bool $expired;

    /**
     * TransientData constructor.
     *
     * @param string $name The name of the transient data
     * @param T $value The value of the transient data
     * @param int $expiration The expiration time in seconds
     */
    public function __construct(
        private string $name,
        #[SensitiveParameter]
        private mixed $value,
        private int $expiration
    ) {
        $this->expired = $this->expiration > 0 && time() > $this->expiration;
        if ($this->expired) {
            $this->value = null; // @phpstan-ignore-line
        }
    }

    /**
     * @return string The name of the transient data
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return T The value of the transient data
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return int The expiration time in seconds
     */
    public function getExpiration(): int
    {
        return $this->expiration;
    }

    /**
     * @return bool Indicates whether the transient data has expired
     */
    public function isExpired(): bool
    {
        return $this->expired;
    }

    /**
     * Creates a new instance of TransientData with the specified expiration time.
     *
     * @param int $expiration The new expiration time in seconds
     * @return static<mixed> A new instance of TransientData with the updated expiration time
     */
    public function withExpiration(int $expiration): self
    {
        if ($expiration !== 0 && ($expiration + 30) < time()) {
            $expiration += time();
        }
        $obj = clone $this;
        $obj->{'expiration'} = $expiration;
        $obj->{'expired'} = $expiration > 0 && time() > $expiration;
        if ($obj->expired) {
            $obj->{'value'} = null;  // @phpstan-ignore-line
        }
        return $obj;
    }

    /**
     * Serializes the TransientData object into a string representation.
     *
     * @return string The serialized string representation of the TransientData object, or null if serialization fails
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * Unserializes the given data and populates the properties of the TransientData object.
     *
     * @param string $data The serialized data to unserialize
     */
    public function unserialize(string $data) : void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * Serializes the TransientData object into an array representation.
     *
     * @return array{
     *     name: string,
     *     value: mixed,
     *     expiration: int
     * } The serialized array representation of the TransientData object
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'expiration' => $this->expiration,
        ];
    }

    /**
     * Unserializes the given data and populates the properties of the TransientData object.
     *
     * @param array{
     *     name: string,
     *     value: mixed,
     *     expiration: int
     * } $data The serialized data to unserialize
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->value = $data['value'];
        $this->expiration = (int)$data['expiration'];
        $this->expired = $this->expiration > 0 && time() > $this->expiration;
    }

    /**
     * Retrieves the value of the specified property.
     *
     * @param string $name The name of the property to retrieve
     * @return mixed The value of the specified property, or null if the property does not exist
     */
    public function __get(string $name) : mixed
    {
        return match ($name) {
            'name' => $this->getName(),
            'value' => $this->getValue(),
            'expiration' => $this->getExpiration(),
            'expired' => $this->isExpired(),
            default => null,
        };
    }

    /**
     * Sets the value of the specified property.
     *
     * @param string $name The name of the property to set
     * @param mixed $value The value to set for the property
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'value') {
            $this->value = $value;
            $this->expired = $this->expiration > 0 && time() > $this->expiration;
        }
    }

    /**
     * Checks if the specified property is set.
     *
     * @param string $name The name of the property to check
     * @return bool True if the property is set, false otherwise
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'name', 'expiration', 'expired' => true,
            'value' => isset($this->value),
            default => false,
        };
    }

    /**
     * Unsets the specified property.
     *
     * @param string $name The name of the property to unset
     */
    public function __unset(string $name): void
    {
        if ($name === 'value') {
            $this->value = null; // @phpstan-ignore-line
            $this->expired = true;
        }
    }
}
