<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections;

use Psr\Container\ContainerInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;

class RegistryComponent extends Registry implements RegistryComponentInterface
{
    /**
     * Constructor for the RegistryComponent class.
     *
     * @param array<string, mixed> $definitions
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
        $definitions[RegistryInterface::class] = $this;
        parent::__construct($definitions, $autoWire, $autoResolveParameter, $lockAll);
    }

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                Registry::class,
                self::class,
                RegistryComponentInterface::class,
                ContainerInterface::class,
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }
}
