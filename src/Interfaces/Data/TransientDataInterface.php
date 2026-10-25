<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Data;

/**
 * @template T of mixed
 */
interface TransientDataInterface
{
    /**
     * @return string The name of the transient data
     */
    public function getName(): string;

    /**
     * @return T The value of the transient data
     */
    public function getValue(): mixed;

    /**
     * @return int The expiration time in seconds
     */
    public function getExpiration(): int;

    /**
     * @return bool Indicates whether the transient data has expired
     */
    public function isExpired(): bool;
}
