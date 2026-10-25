<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryNoChainingInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;

/**
 * No chaining value definition class that extends the Value class
 * and overrides the resolveChain method to return null.
 *
 * @template-implements RegistryNoChainingInterface<string, mixed>
 */
class ValueNoChaining extends Value implements RegistryNoChainingInterface
{
    /**
     * @inheritdoc
     */
    public function resolveChain(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return null;
    }
}
