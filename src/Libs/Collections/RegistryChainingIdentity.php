<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections;

use Throwable;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryDefinitionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryNoChainingInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\AbstractDefinition;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\AutoWire;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\ObjectDefinition;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\ReferenceNoChaining;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\Value;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions\ValueNoChaining;
use function array_keys;
use function is_string;

/**
 * Represents a chaining identity for a registry definition,
 * allowing for multiple reference names to be associated with a single definition.
 */
final class RegistryChainingIdentity
{
    /**
     * @var array<string, bool> $identities The reference names to be used for resolving the definition
     */
    private array $identities = [];

    /**
     * @var array<string, bool> BLACKLIST A list of class names that should not be used as reference names
     */
    public const BLACKLIST = [
        RegistryComponentInterface::class => true,
        RegistryDefinitionInterface::class => true,
        RegistryNoChainingInterface::class => true,
        AbstractDefinition::class => true,
        AutoWire::class => true,
        ReferenceNoChaining::class => true,
        ValueNoChaining::class => true,
        ObjectDefinition::class => true,
        Value::class => true,
        self::class => true,
    ];

    /**
     * @param string $identity The identity of the definition
     * @param string[] $names Additional reference names to be used for resolving the definition
     */
    public function __construct(
        public readonly string $identity,
        array $names,
        public readonly bool $lock = false,
        public readonly bool $unlockable = true
    ) {
        foreach ($names as $n) {
            if (!is_string($n) || !$n || $n === $identity) {
                continue;
            }
            if (isset(self::BLACKLIST[$n])) {
                continue;
            }
            $this->identities[$n] = true;
        }
    }

    /**
     * Returns the identity of the definition
     *
     * @return string The identity of the definition
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /**
     * Returns the reference names
     *
     * @return string[] The reference names
     */
    public function getIdentities(): array
    {
        return array_keys($this->identities);
    }

    /**
     * Creates a new instance of the RefChainingKey class
     *
     * @param string $identity The identity of the definition
     * @param string[] $names Additional reference names to be used for resolving the definition
     * @param bool $lock Whether to lock the definition
     * @param bool $unlockable Whether the definition can be unlocked
     * @return self
     */
    public static function create(
        string $identity,
        array $names,
        bool $lock = false,
        bool $unlockable = true,
    ): self {
        return new self($identity, $names, $lock, $unlockable);
    }

    /**
     * Checks if the given name is included in the reference names
     *
     * @param string $name The name to check
     * @return bool True if the name is included, false otherwise
     */
    public function include(string $name): bool
    {
        return isset($this->identities[$name]);
    }

    /**
     * Registers the reference names in the given registry
     *
     * @param RegistryInterface $registry The registry to register the reference names in
     */
    public function register(RegistryInterface $registry): void
    {
        $identity = $this->getIdentity();
        if (!$identity || !$registry->has($identity)) {
            return;
        }
        if ($this->lock && !$registry->locked($identity)) {
            $registry->lock($identity, $this->unlockable);
        }
        try {
            // $unlockable = $registry->unlockable($identity);
            // $lock = $registry->locked($identity);
            // $shouldReferenceOnly = !$unlockable && $lock;
            foreach ($this->identities as $id => $bool) {
                if ($registry->configureCount($id) > 0) {
                    continue;
                }
                if ($registry->has($id)) {
                    if ($registry->locked($id)) {
                        if (!$registry->unlockable($id)) {
                            continue;
                        }
                        $registry->unlock($id);
                    }
                    $registry->remove($id);
                }
                $registry->set($id, ReferenceNoChaining::create($identity));
                if ($this->lock) {
                    $registry->lock($id, $this->unlockable);
                }
            }
        } catch (Throwable) {
            return;
        }
    }
}
