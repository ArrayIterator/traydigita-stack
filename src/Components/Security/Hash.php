<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Security;

use SensitiveParameter;
use function hash;
use function hash_equals;
use function hash_hmac;

/**
 * A utility class for generating and comparing hashes using various algorithms.
 * This class supports MD5, SHA1, SHA256, SHA384, and SHA512 hashing algorithms,
 * as well as HMAC hashing with optional secret and salt keys.
 *
 * @property-read string $secretKey The secret key used for hashing
 * @property-read string $saltKey The salt key used for hashing
 * @property-read string $combinedKey The combined key of secret and salt keys
 */
final class Hash
{
    /**
     * The default separator used for combining the secret and salt keys.
     */
    public const DEFAULT_SEPARATOR = ':';

    /**
     * The hashing algorithm constants.
     */
    public const MD5 = 'md5';

    /**
     * The hashing algorithm constants.
     */
    public const SHA1 = 'sha1';

    /**
     * The hashing algorithm constants.
     */
    public const SHA256 = 'sha256';

    /**
     * The hashing algorithm constants.
     */
    public const SHA384 = 'sha384';

    /**
     * The hashing algorithm constants.
     */
    public const SHA512 = 'sha512';

    /**
     * @var string $secretKey The secret key used for hashing
     */
    private string $secretKey;

    /**
     * @var string $saltKey The salt key used for hashing
     */
    private string $saltKey;

    /**
     * Hash constructor.
     *
     * @param ?string $secretKey
     * @param ?string $saltKey
     */
    public function __construct(
        #[SensitiveParameter]
        ?string $secretKey = null,
        #[SensitiveParameter]
        ?string $saltKey = null
    ) {
        if (!$secretKey && defined('AUTH_KEY')) {
            $secretKey = \AUTH_KEY;
        }
        if (!$saltKey && defined('AUTH_SALT')) {
            $saltKey = \AUTH_SALT;
        }
        $this->secretKey = $secretKey ?? '';
        $this->saltKey = $saltKey ?? '';
    }

    /**
     * Get the secret key
     *
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * Set the secret key
     *
     * @param string $secretKey
     */
    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Get the salt key
     *
     * @return string
     */
    public function getSaltKey(): string
    {
        return $this->saltKey;
    }

    /**
     * Set the salt key
     *
     * @param string $saltKey
     */
    public function setSaltKey(string $saltKey): void
    {
        $this->saltKey = $saltKey;
    }

    /**
     * Get the combined key
     *
     * @param string $separator
     * @return string
     */
    public function getCombinedKey(string $separator = self::DEFAULT_SEPARATOR): string
    {
        return $this->saltKey . $separator  . $this->secretKey;
    }

    /**
     * Generate hash
     *
     * @param string $algo
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function hash(string $algo, string $data, bool $rawOutput = false): string
    {
        return hash($algo, $data, $rawOutput);
    }

    /**
     * Generate MD5 hash
     *
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function md5(string $data, bool $rawOutput = false): string
    {
        return $this->hash(self::MD5, $data, $rawOutput);
    }

    /**
     * Generate SHA1 hash
     *
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function sha1(string $data, bool $rawOutput = false): string
    {
        return $this->hash(self::SHA1, $data, $rawOutput);
    }

    /**
     * Generate SHA256 hash
     *
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function sha256(string $data, bool $rawOutput = false): string
    {
        return $this->hash(self::SHA256, $data, $rawOutput);
    }

    /**
     * Generate SHA384 hash
     *
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function sha384(string $data, bool $rawOutput = false): string
    {
        return $this->hash(self::SHA384, $data, $rawOutput);
    }

    /**
     * Generate SHA512 hash
     *
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function sha512(string $data, bool $rawOutput = false): string
    {
        return $this->hash(self::SHA512, $data, $rawOutput);
    }

    /**
     * Generate HMAC hash
     *
     * @param string $algo
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmac(
        string $algo,
        string $data,
        string $key,
        bool $rawOutput = false
    ): string {
        return hash_hmac($algo, $data, $key, $rawOutput);
    }

    /**
     * Generate HMAC hash using the auth salt
     *
     * @param string $algo
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function hmacSalt(string $algo, string $data, bool $rawOutput = false): string
    {
        return $this->hmac($algo, $data, $this->saltKey, $rawOutput);
    }

    /**
     * Generate HMAC hash using the auth key
     *
     * @param string $algo
     * @param string $data
     * @param bool $rawOutput
     * @return string
     */
    public function hmacKey(string $algo, string $data, bool $rawOutput = false): string
    {
        return $this->hmac($algo, $data, $this->secretKey, $rawOutput);
    }

    /**
     * Generate HMAC hash using the combined key
     *
     * @param string $algo
     * @param string $data
     * @param bool $rawOutput
     * @param ?string $separator
     * @return string
     */
    public function hmacCombined(string $algo, string $data, bool $rawOutput = false, ?string $separator = null): string
    {
        return $this->hmac(
            $algo,
            $data,
            $this->getCombinedKey($separator??self::DEFAULT_SEPARATOR),
            $rawOutput
        );
    }

    /**
     * Generate HMAC MD5 hash
     *
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmacMd5(string $data, string $key, bool $rawOutput = false): string
    {
        return $this->hmac(self::MD5, $data, $key, $rawOutput);
    }

    /**
     * Generate HMAC SHA1 hash
     *
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmacSha1(string $data, string $key, bool $rawOutput = false): string
    {
        return $this->hmac(self::SHA1, $data, $key, $rawOutput);
    }

    /**
     * Generate HMAC SHA256 hash
     *
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmacSha256(string $data, string $key, bool $rawOutput = false): string
    {
        return $this->hmac(self::SHA256, $data, $key, $rawOutput);
    }

    /**
     * Generate HMAC SHA384 hash
     *
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmacSha384(string $data, string $key, bool $rawOutput = false): string
    {
        return $this->hmac(self::SHA384, $data, $key, $rawOutput);
    }

    /**
     * Generate HMAC SHA512 hash
     *
     * @param string $data
     * @param string $key
     * @param bool $rawOutput
     * @return string
     */
    public function hmacSha512(string $data, string $key, bool $rawOutput = false): string
    {
        return $this->hmac(self::SHA512, $data, $key, $rawOutput);
    }

    /**
     * Compare two hashes
     *
     * @param string $hash1
     * @param string $hash2
     * @return bool
     */
    public function equals(string $hash1, string $hash2): bool
    {
        return $hash1 && $hash2 && hash_equals($hash1, $hash2);
    }

    /**
     * Magic method to handle property access on the Hash class.
     * It returns the value of the secret key, salt key, or combined key based on the property name.
     *
     * @param string $name The name of the property being accessed
     * @return mixed The value of the property
     */
    public function __get(string $name)
    {
        return match ($name) {
            'secretKey' => $this->getSecretKey(),
            'saltKey' => $this->getSaltKey(),
            'combinedKey' => $this->getCombinedKey(),
            default => null
        };
    }

    /**
     * Magic method to handle property setting on the Hash class.
     * It sets the value of the secret key or salt key based on the property name.
     *
     * @param string $name The name of the property being set
     * @param mixed $value The value to set for the property
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'secretKey':
                if (is_string($value)) {
                    $this->setSecretKey($value);
                }
                break;
            case 'saltKey':
                if (is_string($value)) {
                    $this->setSaltKey($value);
                }
                break;
        }
    }

    /**
     * Magic method to handle property existence checks on the Hash class.
     * It checks if the secret key, salt key, or combined key is set based on the property name.
     *
     * @param string $name The name of the property being checked
     * @return bool True if the property is set, false otherwise
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'secretKey', 'saltKey', 'combinedKey' => true,
            default => false
        };
    }

    /**
     * Magic method to handle property unsetting on the Hash class.
     * It does not allow unsetting of the secret key or salt key, as they are essential for hashing.
     *
     * @param string $name The name of the property being unset
     */
    public function __unset(string $name): void
    {
        // void unset for secretKey and saltKey, as they are essential for hashing
    }

    /**
     * Magic method to provide debug information for the Hash class.
     * It returns an array of object properties, with the secret key and salt key redacted for security.
     *
     * @return array<string, mixed> An array of object properties with sensitive information redacted
     */
    public function __debugInfo(): array
    {
        $vars = get_object_vars($this);
        $vars['secretKey'] = '<redacted>';
        $vars['saltKey'] = '<redacted>';
        return $vars;
    }
}
