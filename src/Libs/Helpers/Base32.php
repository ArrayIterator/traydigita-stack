<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function chr;
use function preg_replace;
use function str_repeat;
use function strlen;
use function strtoupper;
use function unpack;

class Base32
{
    /**
     * @var string The Base32 dictionary used for encoding and decoding
     */
    public const DICTIONARY = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Mapping of Base32 characters to their corresponding values
     */
    public const MAPPING = [
        "=" => 0,
        "A" => 0,
        "B" => 1,
        "C" => 2,
        "D" => 3,
        "E" => 4,
        "F" => 5,
        "G" => 6,
        "H" => 7,
        "I" => 8,
        "J" => 9,
        "K" => 10,
        "L" => 11,
        "M" => 12,
        "N" => 13,
        "O" => 14,
        "P" => 15,
        "Q" => 16,
        "R" => 17,
        "S" => 18,
        "T" => 19,
        "U" => 20,
        "V" => 21,
        "W" => 22,
        "X" => 23,
        "Y" => 24,
        "Z" => 25,
        "2" => 26,
        "3" => 27,
        "4" => 28,
        "5" => 29,
        "6" => 30,
        "7" => 31
    ];

    /**
     * Encode Base32
     *
     * @param string $string
     * @return string|false
     */
    public static function encode(string $string): string|false
    {
        // Empty string results in empty string
        if ('' === $string) {
            return '';
        }
        $encoded = '';
        //Set the initial values
        $n = $bitLen = $val = 0;
        $len = strlen($string);
        //Pad the end of the string - this ensures that there are enough zeros
        $string .= str_repeat(chr(0), 4);
        // Explode string into integers
        $chars = unpack('C*', $string);
        if ($chars === false) {
            return false;
        }
        while ($n < $len || 0 !== $bitLen) {
            //If the bit length has fallen below 5, shift left 8 and add the next character.
            if ($bitLen < 5) {
                $val = $val << 8;
                $bitLen += 8;
                $n++;
                $val += $chars[$n];
            }
            $shift = $bitLen - 5;
            $encoded .= ($n - (int)($bitLen > 8) > $len && 0 === $val) ? '=' : self::DICTIONARY[$val >> $shift];
            $val = $val & ((1 << $shift) - 1);
            $bitLen -= 5;
        }
        return $encoded;
    }

    /**
     * Decode 32
     *
     * @param string $string
     *
     * @return string
     */
    public static function decode(string $string): string
    {
        $string = strtoupper($string);
        $string = preg_replace('~[^A-Z2-7]~', '', $string);
        if (!$string) {
            return '';
        }

        $decoded = '';
        //Set the initial values
        $len = strlen($string);
        $n = 0;
        $bitLen = 5;
        $val = self::MAPPING[$string[0]];
        while ($n < $len) {
            if ($bitLen < 8) {
                $val = $val << 5;
                $bitLen += 5;
                $n++;
                $ext = $string[$n] ?? '=';
                if ('=' === $ext) {
                    $n = $len;
                }
                $val += self::MAPPING[$ext];
            } else {
                $shift = $bitLen - 8;
                $decoded .= chr($val >> $shift);
                $val = $val & ((1 << $shift) - 1);
                $bitLen -= 8;
            }
        }

        return $decoded;
    }

    /**
     * Validate the Base32 key
     *
     * @param string $key The Base32 key to validate
     * @return bool True if the key is valid, false otherwise
     */
    public static function valid(mixed $key): bool
    {
        if (!is_string($key)) {
            return false;
        }
        return (bool) preg_match('/^[A-Z2-7]+$/', $key);
    }
}
