<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use Psr\Log\AbstractLogger;
use TrayDigita\WP\Stack\Source\Components\Logger\Traits\TraitLogger;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LoggerInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;

class Logger extends AbstractLogger implements LoggerInterface, RegistryComponentInterface
{
    use TraitLogger;

    /**
     * The default number of cycles for the logger.
     */
    public const DEFAULT_CYCLES = 8;

    /**
     * The minimum number of cycles for the logger.
     */
    public const MIN_CYCLES = 2;

    /**
     * The maximum number of cycles for the logger.
     */
    public const MAX_CYCLES = 32;

    /**
     * The default name for the logger.
     */
    public const DEFAULT_NAME = 'default';

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                LoggerInterface::class,
                \Psr\Log\LoggerInterface::class,
                self::class,
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }
}
