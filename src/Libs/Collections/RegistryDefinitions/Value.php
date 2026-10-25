<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;

class Value extends AbstractDefinition
{
    /**
     * @param mixed $value The value to be used for resolving the definition
     */
    final public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    final public function isResolved(): bool
    {
        return true;
    }

    /**
     * Creates a new instance of the ValueDefinition class
     *
     * @param mixed $value The value to be used for resolving the definition
     * @return static
     */
    public static function create(mixed $value): static
    {
        return new static($value);
    }

    /**
     * @inheritdoc
     */
    public function value(RegistryInterface $registry, bool $autoResolveParameters = true): mixed
    {
        return $this->value;
    }
}
