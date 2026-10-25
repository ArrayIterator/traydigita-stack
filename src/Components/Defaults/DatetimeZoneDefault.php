<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Defaults;

use DateTimeZone;
use Exception;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;
use function date_default_timezone_get;

class DatetimeZoneDefault extends DateTimeZone implements RegistryComponentInterface
{
    /**
     * @var self $instance The singleton instance of the DatetimeZoneDefault class
     */
    private static self $instance;

    /**
     * Static method to get the default instance
     *
     * @return self
     */
    public static function default(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Check if the current timezone is UTC
     * @return bool
     */
    public function isUtc(): bool
    {
        $name = $this->getName();
        $upperName = strtoupper($name);
        return $upperName === 'UTC' || $upperName === 'GMT';
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            parent::__construct(date_default_timezone_get());
        } catch (Exception) {
            /** @noinspection PhpUnhandledExceptionInspection */
            parent::__construct('UTC');
        }
        self::$instance ??= $this;
    }

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                self::class,
                DateTimeZone::class
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }
}
