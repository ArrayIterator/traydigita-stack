<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Auth;

use TrayDigita\WP\Stack\Source\Exceptions\RangeException;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Base32;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Ctype;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Random;
use function chr;
use function hash_hmac;
use function hex2bin;
use function implode;
use function is_string;
use function max;
use function mt_rand;
use function ord;
use function strlen;
use function substr;
use function time;

/**
 * OATH - TOTP (Initiative for Open Authentication - Time-based One-time Password Algorithm)
 *
 * @link https://tools.ietf.org/html/rfc6238
 *
 * @link https://tools.ietf.org/html/rfc4226
 * @link https://en.wikipedia.org/wiki/HMAC-based_One-time_Password_Algorithm
 */
class Totp
{
    public const DEFAULT_TIMESPAN = 30;

    public const DEFAULT_STEP = 1;

    /**
     * @var int<1, max> $defaultTimespan The default timespan in seconds for TOTP generation (default: 30 seconds)
     */
    private int $defaultTimespan = self::DEFAULT_TIMESPAN;

    /**
     * @var int<0, max> $defaultStep The default step window for TOTP verification (default: 1)
     */
    private int $defaultStep = self::DEFAULT_STEP;

    /**
     * TOTP constructor.
     *
     * @param int<1, max> $defaultTimespan The default timespan in seconds for TOTP generation (optional)
     * @param int<0, max> $defaultStep The default step window for TOTP verification (optional)
     */
    public function __construct(
        int $defaultTimespan = self::DEFAULT_TIMESPAN,
        int $defaultStep = self::DEFAULT_STEP
    ) {
        $this->setDefaultTimespan($defaultTimespan);
        $this->setDefaultStep($defaultStep);
    }

    /**
     * Get the default timespan for TOTP generation.
     *
     * @return int<1, max> The default timespan in seconds
     */
    public function getDefaultTimespan(): int
    {
        return $this->defaultTimespan;
    }

    /**
     * Set the default timespan for TOTP generation.
     *
     * @param int<1, max> $defaultTimespan The default timespan in seconds
     */
    public function setDefaultTimespan(int $defaultTimespan): void
    {
        $this->defaultTimespan = max(1, $defaultTimespan);
    }

    /**
     * Get the default step window for TOTP verification.
     *
     * @return int<0, max> The default step window
     */
    public function getDefaultStep(): int
    {
        return $this->defaultStep;
    }

    /**
     * Set the default step window for TOTP verification.
     *
     * @param int<0, max> $defaultStep The default step window
     */
    public function setDefaultStep(int $defaultStep): void
    {
        $this->defaultStep = max(0, $defaultStep);
    }

    /**
     * Generate a new secret key
     * OATH HOTP (HMAC-based One-time Password Algorithm) require at least 128 bits (16 bytes) of secret key
     *  - 128 bits (16 bytes)
     *  - 160 bits (20 bytes)
     *  - 256 bits (32 bytes)
     *
     * @param int<16, 32> $length Length of the secret key in bytes (default: 16)
     * @return string generated secret key
     * @throws RangeException Invalid length, length must be at least 16 and at most 32
     */
    public function generateKey(int $length = 16): string
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($length < 16 || $length > 32) {
            throw new RangeException('Invalid length, length must be at least 16 and at most 32');
        }
        $randomBytes = Random::bytes($length);
        $random = Base32::encode($randomBytes);
        if (!$random) {
            throw new RangeException('Failed to generate a valid Base32 encoded key');
        }
        if ($random > 32) {
            $random = substr($random, 0, 32);
        } else {
            $base32Length = strlen($random) - 1;
            while (strlen($random) < 32) {
                $random .= Base32::DICTIONARY[mt_rand(0, $base32Length)];
            }
        }
        return $random;
    }

    /**
     * Validate the secret key
     *
     * @param mixed $key The secret key to validate
     * @return bool True if the key is valid, false otherwise
     */
    public function isValidKey(mixed $key): bool
    {
        if (!is_string($key)) {
            return false;
        }
        $length = strlen($key);
        if ($length >= 16 && $length <= 32) {
            return Base32::valid($key);
        }
        return false;
    }

    /**
     * Generate HOTP
     *
     * @param string $key the shared secret
     * @param int<0, max> $movingFactor the counter, time, or other value that changes on a per use basis.
     * @return string HOTP that is a 20-byte string (160 bits) of the HMAC-SHA-1 result
     * @ref https://datatracker.ietf.org/doc/html/rfc4226#section-5.3
     * @throws RangeException if the moving factor is negative
     */
    public function generateHmacOTP(string $key, int $movingFactor): string
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($movingFactor < 0) {
            throw new RangeException(
                'Invalid counter, counter must be a positive integer'
            );
        }
        $counter = [];
        for ($i = 7; $i >= 0; $i--) {
            $counter[$i] = chr($movingFactor & 0xff);
            $movingFactor >>= 8;
        }
        $decodedKey = Base32::decode($key);
        return hash_hmac('sha1', implode('', $counter), $decodedKey, true);
    }

    /**
     * Verify the provided TOTP code against the secret key.
     *
     * @param mixed $secret The secret key to verify against
     * @param mixed $code The TOTP code to verify
     * @param int<1, max>|null $timespan The timespan in seconds for TOTP generation (optional)
     * @param int<0, max>|null $step The step window for TOTP verification (optional)
     * @return bool True if the code is valid, false otherwise
     */
    public function verify(
        mixed $secret,
        mixed $code,
        ?int $timespan = null,
        ?int $step = null
    ): bool {
        if (!$this->isValidKey($secret)) {
            return false;
        }
        $timespan ??= $this->getDefaultTimespan();
        $step ??= $this->getDefaultStep();
        return $this->verifyTOTP($secret, $code, $timespan, $step);
    }

    /**
     * Truncate the HMAC-SHA-1
     *
     * @param string $hmac HMAC result
     * @return int truncated HOTP
     *
     * @ref https://datatracker.ietf.org/doc/html/rfc4226#section-5.4
     * @ref https://datatracker.ietf.org/doc/html/rfc4226#section-5.2
     * @private
     * @throws RangeException if the HMAC result is not 20 bytes
     */
    private function truncateHmac(string $hmac): int
    {
        if (strlen($hmac) === 40 && Ctype::xdigit($hmac)) {
            $hmac = hex2bin($hmac);
        }
        if (!$hmac || strlen($hmac) !== 20) {
            throw new RangeException('Invalid HMAC result, HMAC result must be 20 bytes');
        }
        // HOTP(K,C) = Truncate(HMAC-SHA-1(K,C))
        $offset = ord($hmac[19]) & 0xf;
        $bin_code = (ord($hmac[$offset]) & 0x7f) << 24
            | (ord($hmac[$offset + 1]) & 0xff) << 16
            | (ord($hmac[$offset + 2]) & 0xff) << 8
            | (ord($hmac[$offset + 3]) & 0xff);
        return $bin_code % 1_000_000;
    }

    /**
     * Calculates the checksum using the credit card algorithm.
     * This algorithm has the advantage that it detects any single
     * mistyped digit and any single transposition of
     * adjacent digits.
     *
     * @param int $num the number to calculate the checksum for
     * @param int $digits number of significant places in the number
     *
     * @return int the checksum of num
     * @private
     */
    private function calcChecksum(int $num, int $digits): int
    {
        $doubleDigit = true;
        $total = 0;
        $dictionary = [0, 2, 4, 6, 8, 1, 3, 5, 7, 9];
        $modulo = count($dictionary);
        while ($digits-- > 0) {
            $digit = $num % $modulo;
            $num = (int)($num / $modulo);
            if ($doubleDigit) {
                $digit = $dictionary[$digit];
            }
            $total += $digit;
            $doubleDigit = !$doubleDigit;
        }
        $result = $total % $modulo;
        if ($result > 0) {
            $result = $modulo - $result;
        }
        return $result;
    }

    /**
     * Generate TOTP
     *
     * @param string $key the shared secret of base32 encoded
     * @param positive-int $movingFactor the counter, time
     * @param int<6,8> $codeDigits number of digits in the OTP
     * @param bool $addChecksum add checksum to the OTP
     * @param int<0, max> $window the time step window
     * @return string TOTP
     */
    public function generate(
        string $key,
        int $movingFactor = 30,
        int $codeDigits = 6,
        bool $addChecksum = false,
        int $window = 0
    ): string {
        if ($movingFactor < 0) {
            throw new RangeException(
                'Invalid counter, counter must be a positive integer'
            );
        }
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($codeDigits < 6 || $codeDigits > 8) {
            throw new RangeException('Invalid code digits, code digits must be between 6 and 8');
        }
        $DIGITS_POWER = [1, 10, 100, 1000, 10000, 100000, 1000000, 10000000, 100000000];
        $counter = (int)(time() / $movingFactor) + $window;
        if ($counter < 0) {
            throw new RangeException('Invalid counter, counter must be a positive integer');
        }
        $digits = $addChecksum ? ($codeDigits + 1) : $codeDigits;
        $h_otp = $this->generateHmacOTP($key, $counter);
        $truncated = $this->truncateHmac($h_otp);
        $otp = $truncated % $DIGITS_POWER[$codeDigits];
        if ($addChecksum) {
            $otp = ($otp * 10) + $this->calcChecksum($otp, $codeDigits);
        }
        $otp = (string)$otp;
        while (strlen($otp) < $digits) {
            $otp = '0' . $otp;
        }
        return $otp;
    }

    /**
     * Verify TOTP
     *
     * @param string $key the shared secret of base32 encoded
     * @param numeric $code the OTP
     * @param int<1, max> $timespan in seconds of the time window
     * @param int<0, max> $step the time step window
     * @return bool true if valid
     */
    public function verifyTOTP(
        string $key,
        mixed $code,
        int $timespan = self::DEFAULT_TIMESPAN,
        int $step = self::DEFAULT_STEP
    ): bool {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if ($timespan < 1) {
            return false;
            // throw new RangeException('Invalid timespan, timespan must be a positive integer');
        }
        if (!Ctype::digit($code)) {
            return false;
        }
        $code = (string)$code;
        if (strlen($code) > 9 || strlen($code) < 6) { // 9 is additional checksum
            return false;
        }
        $step = max($step, 0);
        $addChecksum = strlen($code) % 2 !== 0;
        $codeDigits = $addChecksum ? strlen($code) - 1 : strlen($code);
        if ($codeDigits < 6 || $codeDigits > 8) {
            return false;
        }
        if ($step === 0) {
            return $code === $this->generate($key, $timespan, $codeDigits, $addChecksum);
        }
        for ($window = -$step; $window <= $step; $window++) {
            if ($window <= 0) {
                continue;
            }
            $totp = $this->generate($key, $timespan, $codeDigits, $addChecksum, $window);
            if ($code === $totp) {
                return true;
            }
        }
        return false;
    }
}
