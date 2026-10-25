<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use Stringable;
use Throwable;
use function chr;
use function function_exists;
use function is_bool;
use function mt_rand;
use function openssl_random_pseudo_bytes;
use function rand;
use function random_bytes;
use function random_int;
use function strlen;

/**
 * Random class for generating random strings, bytes, and integers.
 * Supports generating random characters from specified character sets,
 * including hexadecimal, alphanumeric, and special characters.
 */
final class Random implements Stringable
{
    /**
     * Hexadecimal chars
     */
    public const HEX = 'abcdef0123456789';

    /**
     * Alphanumeric chars
     */
    public const ALPHA_NUMERIC = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Special chars
     */
    public const SPECIAL_CHAR = '~`!@#$%^&*()_-+={[}]|\:;"\'<,>.?/';

    /**
     * Default chars contains alphanumeric and special chars
     */
    public const DEFAULT_CHAR = self::ALPHA_NUMERIC . self::SPECIAL_CHAR;

    /**
     * Random chars
     *
     * @param int $length
     * @param string|null $char
     * @return string
     */
    public static function char(int $length = 64, ?string $char = self::DEFAULT_CHAR) : string
    {
        if ($length < 1) {
            return '';
        }

        $chars = $char?:self::DEFAULT_CHAR;
        $characterLength = strlen($chars);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $chars[rand(0, $characterLength - 1)];
        }
        return $randomString;
    }

    /**
     * Generate random bytes
     *
     * @param int $bytes
     * @return string
     */
    public static function bytes(int $bytes) : string
    {
        static $pseudo = null;

        if ($bytes < 1) {
            return '';
        }

        try {
            return random_bytes($bytes);
        } catch (Throwable) {
            if (!is_bool($pseudo)) {
                $pseudo = function_exists('openssl_random_pseudo_bytes');
            }
            try {
                if ($pseudo) {
                    return openssl_random_pseudo_bytes($bytes);
                }
            } catch (Throwable) {
                // pass
            }
            $random = '';
            while (strlen($random) < $bytes) {
                $random .= chr(mt_rand(0, 255));
            }
            return $random;
        }
    }

    /**
     * Generate a random hex string
     *
     * @param int $length
     * @return string
     */
    public static function hex(int $length) : string
    {
        return self::char($length, self::HEX);
    }

    /**
     * Generate a random int
     *
     * @param int $min
     * @param int $max
     * @return int
     */
    public static function int(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (Throwable) {
            return mt_rand($min, $max);
        }
    }

    /**
     * @inheritdoc
     */
    public function __toString() : string
    {
        return self::char();
    }
}
