<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Data;

use TrayDigita\WP\Stack\Source\Components\Data\Traits\OptionTrait;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Data\OptionInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;
use function sprintf;
use function trim;

/**
 * Class SiteOptions
 *
 * This class provides a mechanism to manage site options in a WordPress environment.
 * It implements the OptionInterface and uses the OptionTrait for common option handling functionality.
 */
final class SiteOptions implements OptionInterface, RegistryComponentInterface
{
    use OptionTrait {
        __construct as private constructOption;
    }

    /**
     * The name of the option in the database.
     * @var string
     */
    public const string OPTION_NAME = 'site_options';

    /**
     * The suffix name for the option in the database.
     * @var string
     */
    private string $suffixName = '';

    /**
     * SiteOptions constructor.
     *
     * @param string $suffixName The suffix name for the option in the database.
     */
    public function __construct(string $suffixName = '')
    {
        $this->suffixName = trim($suffixName);
        $this->constructOption();
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return self::TYPE_SITE_OPTION;
    }

    /**
     * @inheritdoc
     */
    public function getOptionName(): string
    {
        return sprintf('%s%s%s', self::PREFIX, self::OPTION_NAME, $this->suffixName);
    }

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry): RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                self::class,
                OptionInterface::class,
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }
}
