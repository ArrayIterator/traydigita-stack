<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;

interface RegistryComponentInterface
{
    /**
     * Register the chaining component to the registry
     *
     * @param string $id
     * @param RegistryInterface $registry
     * @return ?RegistryChainingIdentity
     */
    public static function registerSelf(string $id, RegistryInterface $registry) : ?RegistryChainingIdentity;
}
