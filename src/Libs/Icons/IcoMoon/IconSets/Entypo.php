<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\IconSets;

use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\WebComponent;
use function dirname;

/**
 * @template-extends WebComponent<non-empty-lowercase-string>
 */
final class Entypo extends WebComponent
{
    /**
     * The unique identifier for the Entypo icon set.
     */
    public const string ID = 'entypo';

    /**
     * Orbiters constructor.
     */
    public function __construct()
    {
        [
            'palettes' => $palettes,
            'formats' => $formats,
            'glyphs' => $glyphs
        ] = require dirname(__DIR__) . '/data/entypo.icomoon.php';

        parent::__construct($palettes, $formats, $glyphs);
    }

    /**
     * @return string
     */
    public static function id(): string
    {
        return self::ID;
    }
}
