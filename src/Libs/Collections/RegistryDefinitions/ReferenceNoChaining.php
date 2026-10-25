<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryNoChainingInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;

/**
 * No chaining value definition class that extends the Reference class and overrides
 * the resolveChain method to return null.
 * @template-implements RegistryNoChainingInterface<string, mixed>
 */
class ReferenceNoChaining extends Reference implements RegistryNoChainingInterface
{
    /**
     * @inheritdoc
     */
    public function resolveChain(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return null;
    }
}
