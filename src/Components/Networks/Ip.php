<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Networks;

use Stringable;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\RawParams;
use function bin2hex;
use function bindec;
use function count;
use function dechex;
use function explode;
use function filter_var;
use function hex2bin;
use function hexdec;
use function inet_ntop;
use function inet_pton;
use function ip2long;
use function is_int;
use function is_numeric;
use function is_string;
use function long2ip;
use function min;
use function pow;
use function preg_match;
use function str_contains;
use function strcmp;
use function strlen;
use function strrpos;
use function substr;
use function substr_count;
use function substr_replace;
use function trim;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Filter IP
 */
final class Ip implements Stringable
{
    /**
     * Default empty IP address
     */
    public const DEFAULT_EMPTY_IP = '0.0.0.0';

    /**
     * Default local IP address
     */
    public const DEFAULT_LOCAL_IP = '127.0.0.1';

    /**
     * IP version 4
     */
    public const IP4 = 4;

    /**
     * IP version 6
     */
    public const IP6 = 6;

    /**
     * Regex for matching local IPv4 addresses
     */
    public const IPV4_LOCAL_REGEX = '~^
        (?:
            (?:0?[01]?0|127|255)\.(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])
            | 192\.168
            | 172\.16
        )
        (?:\.(?:[01]?[0-9]{1,2}|2[0-4][0-9]|25[0-5])){2}
    $~x';

    /**
     * @var int $version The IP version (0) if invalid
     * @phpstan-var 0|4|6
     */
    private int $version;

    /**
     * @var bool $isBogon Whether the IP is a bogon IP
     */
    private bool $isBogon;

    /**
     * @param string $address The IP address to filter
     */
    public readonly string $address;

    /**
     * Ip constructor.
     * If empty address is provided, it will use the REMOTE_ADDR from the server request or $_SERVER superglobal.
     *
     * @param string|null $address The IP address to filter
     */
    public function __construct(?string $address = null)
    {
        if ($address) {
            $this->address = trim($address);
        } else {
            $this->address = RawParams::server('REMOTE_ADDR')
                ?? (Is::cli() ? self::DEFAULT_LOCAL_IP: self::DEFAULT_EMPTY_IP);
        }
    }

    /**
     * Create a new Ip instance with the given IP address
     *
     * @param string $ip The IP address to filter
     * @return Ip A new Ip instance with the given IP address
     */
    public function withIp(string $ip): Ip
    {
        return new self($ip);
    }

    /**
     * Get the IP address
     *
     * @return string The IP address
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @return positive-int Returns the IP version (4 or 6) or 0 if invalid
     * @phpstan-return 0|4|6
     */
    public function ipVersion(): int
    {
        return $this->version ??= $this->version($this->getAddress()) ?? 0;
    }

    /**
     * Check if the IP address is IPv4
     *
     * @return bool True if the IP address is IPv4, false otherwise
     */
    public function isIpv4(): bool
    {
        return $this->ipVersion() === self::IP4;
    }

    /**
     * Check if the IP address is IPv6
     *
     * @return bool True if the IP address is IPv6, false otherwise
     */
    public function isIpv6(): bool
    {
        return $this->ipVersion() === self::IP6;
    }

    public function isBogon(): bool
    {
        return $this->isBogon ??= $this->isBogonIp($this->getAddress());
    }

    /**
     * Check if the IP address is in the given CIDR range
     *
     * @param string $cidr The CIDR range to check against
     * @return bool True if the IP address is in the CIDR range, false otherwise
     */
    public function inRange(string $cidr): bool
    {
        return $this->ipInRange($this->getAddress(), $cidr);
    }

    /**
     * Filters an IPv4 address
     *
     * @param string $ip
     * @return ?string Returns the filtered IP address, or null if the IP is invalid
     */
    public function filterIpv4(string $ip): ?string
    {
        if (preg_match('/^([01]{8}\.){3}[01]{8}\z/i', $ip)) {
            // binary format 00000000.00000000.00000000.00000000
            $ip = bindec(substr($ip, 0, 8))
                . 'Utils'
                . bindec(substr($ip, 9, 8))
                . '.'
                . bindec(substr($ip, 18, 8))
                . '.'
                . bindec(substr($ip, 27, 8));
        } elseif (preg_match('/^([0-9]{3}\.){3}[0-9]{3}\z/i', $ip)) {
            // octet format 777.777.777.777
            $ip = (int)substr($ip, 0, 3) . '.' . (int)substr($ip, 4, 3) . '.'
                . (int)substr($ip, 8, 3) . '.' . (int)substr($ip, 12, 3);
        } elseif (preg_match('/^([0-9a-f]{2}\.){3}[0-9a-f]{2}\z/i', $ip)) {
            // hex format ff.ff.ff.ff
            $ip = hexdec(substr($ip, 0, 2)) . 'Utils' . hexdec(substr($ip, 3, 2)) . '.'
                . hexdec(substr($ip, 6, 2)) . '.' . hexdec(substr($ip, 9, 2));
        }
        if (($ip2long = ip2long($ip)) === false) {
            return null;
        }

        return $ip === long2ip($ip2long) ? $ip : null;
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $ip
     * @return bool True when $ip is a valid ipv4 address
     */
    public function isValidIpv4(string $ip): bool
    {
        return $this->filterIpv4($ip) !== null;
    }

    /**
     * Validates an IPv4 address
     *
     * @param string $ip
     * @return bool True when $ip is a valid local ipv4 address
     */
    public function isLocalIP4(string $ip): bool
    {
        $ip = $this->filterIpv4($ip);
        return $ip && preg_match(self::IPV4_LOCAL_REGEX, $ip);
    }

    /**
     * Validates an IPv6 address
     *
     * @param string $value Value to check against
     * @return bool True when $value is a valid ipv6 address
     *                 False otherwise
     */
    public function isValidIpv6(string $value): bool
    {
        if (strlen($value) < 3) {
            return $value === '::';
        }

        if (str_contains($value, '.')) {
            $last_colon = strrpos($value, ':');
            if (!($last_colon && $this->isValidIpv4(substr($value, $last_colon + 1)))) {
                return false;
            }

            $value = substr($value, 0, $last_colon) . ':0:0';
        }

        if (str_contains($value, '::') === false) {
            return (bool)preg_match('/\A(?:[a-f0-9]{1,4}:){7}[a-f0-9]{1,4}\z/i', $value);
        }

        $colonCount = substr_count($value, ':');
        if ($colonCount < 8) {
            return (bool)preg_match('/\A(?::|(?:[a-f0-9]{1,4}:)+):(?:(?:[a-f0-9]{1,4}:)*[a-f0-9]{1,4})?\z/i', $value);
        }

        // special case with ending or starting double colon
        if ($colonCount === 8) {
            return (bool)preg_match('/\A(?:::)?(?:[a-f0-9]{1,4}:){6}[a-f0-9]{1,4}(?:::)?\z/i', $value);
        }

        return false;
    }

    /**
     * Check if the given IP address is a bogon IP
     *
     * @param string $ip The IP address to check
     * @return bool True if the IP address is a bogon IP, false otherwise
     */
    public function isBogonIp(string $ip): bool
    {
        return $this->version($ip) && !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Convert ipv4 cidr to range
     *
     * @param string $cidr eg 127.0.0.1/24
     * @return ?array{0: string, 1: string} start & end ip address
     */
    public function ipv4CIDRToRange(string $cidr): ?array
    {
        if (count(($cidr = explode('/', $cidr))) !== 2) {
            return null;
        }
        if (($ip = trim($cidr[0])) === ''
            || ($range = trim($cidr[1])) === ''
            || str_contains($range, '.')
            || !is_numeric($range)
            || $range > 32
            || $range < 0
            || !$this->isValidIpv4($ip)
        ) {
            return null;
        }
        $ips = explode('.', $ip);
        if (count($ips) !== 4) {
            return null;
        }
        foreach ($ips as $ip_address) {
            if ($ip_address === '') {
                return null;
            }
            if (str_contains('.', $ip_address)
                || !is_numeric($ip_address)
                || $ip_address > 255
                || $ip_address < 0
            ) {
                return null;
            }
        }
        $range = (int)$range;
        $ip_long = ip2long($ip);
        if ($ip_long === false) {
            return null;
        }
        $pow = pow(2, (32 - $range));
        if (!is_int($pow)) {
            return null;
        }
        $ip0 = ($ip_long) & ((-1 << (32 - $range)));
        $long_0 = long2ip($ip0);
        if ($long_0 === false) {
            return null;
        }
        $long_1 = long2ip($ip0 + $pow - 1);
        if ($long_1 === false) {
            return null;
        }
        return [
            $long_0,
            $long_1
        ];
    }

    /**
     * Convert ipv6 cidr to range
     *
     * @param string $cidr 2001:100::/24
     * @return ?array{0: string, 1: string} start & end ip address
     */
    public function ipv6CIDRToRange(string $cidr): ?array
    {
        if (count(($cidr = explode('/', trim($cidr)))) !== 2) {
            return null;
        }
        if (($ip = trim($cidr[0])) === ''
            || ($range = trim($cidr[1])) === ''
            || str_contains($range, '.')
            || !is_numeric($range)
            || $range < 0
            || $range > 128
            || !$this->isValidIpv6($ip)
        ) {
            return null;
        }

        $firstAddrBin = inet_pton($ip);
        // fail return null
        if ($firstAddrBin === false
            || !($firstAddr = inet_ntop($firstAddrBin))
        ) {
            return null;
        }
        $flexBits = 128 - ((int)$range);
        // Build the hexadecimal string of the last address
        $lastAddrHex = bin2hex($firstAddrBin);
        // start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flexBits > 0) {
            // Get the character at this position
            $orig = substr($lastAddrHex, $pos, 1);
            // Convert it to an integer
            $originalVal = hexdec($orig);
            // OR it with (2^flexBits)-1, with flexBits limited to 4 at a time
            $newVal = $originalVal | (pow(2, min(4, $flexBits)) - 1);
            // Convert it back to a hexadecimal character
            $new = dechex($newVal);
            // And put that character back in the string
            $lastAddrHex = substr_replace($lastAddrHex, $new, $pos, 1);
            // process one nibble, move to previous position
            $flexBits -= 4;
            $pos -= 1;
        }
        $lastAddrBin = hex2bin($lastAddrHex);
        if ($lastAddrBin === false) {
            return null;
        }
        $lastAddr = inet_ntop($lastAddrBin);
        if (!$lastAddr) {
            return null;
        }
        return [$firstAddr, $lastAddr];
    }

    /**
     * Get IP Version
     *
     * @param string|mixed $ip
     * @return ?int Returns the IP version (4 or 6) or null if invalid
     * @phpstan-return 4|6|null
     */
    public function version(mixed $ip): ?int
    {
        if (!is_string($ip)) {
            return null;
        }
        if (str_contains($ip, ':')) {
            return $this->isValidIpv6($ip) ? self::IP6 : null;
        }
        return str_contains($ip, '.') && $this->isValidIpv4($ip)
            ? self::IP4
            : null;
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    public function ipInRange(string $ip, string $cidr): bool
    {
        $version = $this->version($ip);
        if (!$version) {
            return false;
        }
        $range = ($version === self::IP4) ? $this->ipv4CIDRToRange($cidr) : $this->ipv6CIDRToRange($cidr);
        if (!$range) {
            return false;
        }

        if ($version === self::IP4) {
            $current = ip2long($ip);
            return $current >= ip2long($range[0]) && $current <= ip2long($range[1]);
        }
        $inet_ip = inet_pton($ip);
        if ($inet_ip === false) {
            return false;
        }
        $range0 = inet_pton($range[0]);
        if ($range0 === false) {
            return false;
        }
        $range1 = inet_pton($range[1]);
        if ($range1 === false) {
            return false;
        }
        return strcmp($inet_ip, $range0) >= 0 &&
            strcmp($inet_ip, $range1) <= 0;
    }

    public function __toString(): string
    {
        return $this->getAddress();
    }
}
