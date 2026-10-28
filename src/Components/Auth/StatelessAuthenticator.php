<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Auth;

use DateInterval;
use DateTimeInterface;
use TrayDigita\WP\Stack\Source\Components\Auth\Payload\AuthenticatorPayload;
use TrayDigita\WP\Stack\Source\Components\Factory\UserFactory;
use TrayDigita\WP\Stack\Source\Components\Networks\ClientBrowserType;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgent;
use TrayDigita\WP\Stack\Source\Components\Security\Hash;
use TrayDigita\WP\Stack\Source\Interfaces\Data\UserFactoryInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Data\UserInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DatetimeImmutableUnit;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Duration;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Random;
use function bin2hex;
use function hash_equals;
use function hex2bin;
use function implode;
use function is_array;
use function pack;
use function parse_url;
use function strlen;
use function substr;
use function trim;
use function unpack;

/**
 * StatelessAuthenticator class for generating and validating stateless authentication tokens.
 */
class StatelessAuthenticator
{
    /**
     * The separator used in the token payload
     */
    public const SEPARATOR = ':';

    /**
     * The length of the token in characters (hex representation)
     */

    public const LENGTH = 400; // 400 characters (200 bytes) in hex representation

    /**
     * The minimum expiration time in seconds
     */
    public const MIN_EXPIRATION = 15; // 15 seconds

    /**
     * The default expiration time in seconds
     */
    public const DEFAULT_EXPIRATION = 3600; // 1 hour

    /**
     * @var string $keySeparator The key separator used in the token payload
     */
    private string $keySeparator = self::SEPARATOR;

    /**
     * @var Duration $defaultExpiration The default expiration duration
     */
    public readonly Duration $defaultExpiration;

    /**
     * @var Duration $minExpiration The minimum expiration duration
     */
    public readonly Duration $minExpiration;

    /**
     * @var string $saltBinary The salt binary from the auth salt
     */
    private string $saltBinary;

    /**
     * @var UserFactoryInterface $userFactory The user factory instance
     */
    private UserFactoryInterface $userFactory;

    /**
     * @var UserAgent $userAgent The user agent instance
     */
    private UserAgent $userAgent;

    /**
     * StatelessAuthenticator constructor.
     * @param Hash $hash The hash instance containing secret and salt keys
     * @param UserFactoryInterface|null $userFactory The user factory instance
     * @param ?UserAgent $userAgent The user agent instance
     * @param string $keySeparator The key separator (default is ':')
     */
    public function __construct(
        public readonly Hash $hash,
        ?UserFactoryInterface $userFactory = null,
        ?UserAgent $userAgent = null,
        string $keySeparator = self::SEPARATOR
    ) {
        $this->setKeySeparator($keySeparator);
        if ($userAgent) {
            $this->userAgent = $userAgent;
        }
        if ($userFactory) {
            $this->userFactory = $userFactory;
        }
        $this->defaultExpiration = new Duration(seconds: self::DEFAULT_EXPIRATION, immutable: true);
        $this->minExpiration = new Duration(seconds: self::MIN_EXPIRATION, immutable: true);
    }

    /**
     * Get the UserAgent instance, creating it if not already set
     *
     * @return UserAgent The UserAgent instance
     */
    public function getUserAgent(): UserAgent
    {
        return $this->userAgent ??= new UserAgent(DataFilter::removeMagicQuotes($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /**
     * @param UserAgent $userAgent
     */
    public function setUserAgent(UserAgent $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * Get the UserFactoryInterface instance, creating it if not already set
     *
     * @return UserFactoryInterface The UserFactoryInterface instance
     */
    public function getUserFactory() : UserFactoryInterface
    {
        return $this->userFactory ??= new UserFactory();
    }

    /**
     * Create a clone of StatelessAuthenticator with the given UserFactoryInterface
     *
     * @param UserFactoryInterface $userFactory The user factory instance
     * @return self A new instance of StatelessAuthenticator with the specified user factory
     */
    public function withUserFactory(UserFactoryInterface $userFactory): self
    {
        $obj = clone $this;
        $obj->userFactory = $userFactory;
        return $obj;
    }

    /**
     * Get the value of keySeparator
     *
     * @return string
     */
    public function getKeySeparator(): string
    {
        return $this->keySeparator;
    }

    /**
     * Set the value of keySeparator
     *
     * @param string $keySeparator
     * @return void
     */
    public function setKeySeparator(string $keySeparator): void
    {
        $keySeparator = trim($keySeparator);
        if ($keySeparator !== '') {
            $this->keySeparator = $keySeparator;
        }
    }

    /**
     * Hash the given data with the given key using HMAC-SHA256
     *
     * @param string $data
     * @param string $key
     * @param bool $raw
     * @return string
     */
    public function hash(string $data, string $key, bool $raw = false): string
    {
        return $this->hash->hmac(Hash::SHA256, $data, $key, $raw);
    }

    /**
     * Get the current timestamp in milliseconds
     *
     * @return int
     */
    public function getTimestampMillisecond(): int
    {
        return DatetimeImmutableUnit::now()->toMilliSeconds();
    }

    /**
     * Concatenate multiple values into a single string with separator
     *
     * @param int|float|string ...$args
     * @return string
     */
    public function concatSeparator(int|float|string ...$args): string
    {
        return implode($this->getKeySeparator(), $args);
    }

    /**
     * Concatenate multiple values into a single string
     *
     * @param int|float|string ...$args
     * @return string
     */
    public function concat(int|float|string ...$args): string
    {
        return implode('', $args);
    }

    /**
     * Get the salt binary from the auth salt
     *
     * @return string
     */
    public function getSaltBinary(): string
    {
        return $this->saltBinary ??= $this->hash->hmacCombined(
            Hash::SHA256,
            $this->hash->getSaltKey(),
            true,
            $this->getKeySeparator()
        );
    }

    /**
     * Hash the given data with the given random, user and timestamp using HMAC-SHA256
     *
     * @param string $data
     * @param string $random
     * @param UserInterface $user
     * @param int $timestamp
     * @return string
     */
    public function hashInner(
        string $data,
        string $random,
        UserInterface $user,
        int $timestamp
    ): string {
        $key = $this->concatSeparator(
            $random,
            $this->hash->getCombinedKey($this->getKeySeparator()),
            $user->getUsername(),
            $user->getId(),
            $timestamp
        );
        return $this->hash($data, $key, true);
    }

    /**
     * Hash the given data with the given random and timestamp using HMAC-SHA256
     *
     * @param string $data
     * @param string $random
     * @param int $timestamp
     * @return string
     */
    public function hashOuter(
        string $data,
        string $random,
        int $timestamp
    ): string {
        $key = $this->concat(
            $random,
            $this->getSaltBinary(),
            $timestamp
        );
        return $this->hash($data, $key, true);
    }

    /**
     * Create a Duration object from the given duration
     *
     * @param DateInterval|Duration|DateTimeInterface|null $duration
     * @return Duration
     */
    public function createDuration(DateInterval|Duration|DateTimeInterface|null $duration = null): Duration
    {
        if ($duration instanceof DateInterval) {
            $duration = Duration::fromDateInterval($duration, true);
        } elseif ($duration instanceof DateTimeInterface) {
            $duration = Duration::fromDate($duration, true);
        } elseif (!$duration instanceof Duration) {
            $duration = $this->defaultExpiration;
        }
        return $duration->lessThan($this->minExpiration) ? $this->minExpiration : $duration->asImmutable();
    }

    /**
     * Get the path and query from the URI
     *
     * @param string|null $uri
     * @return string
     */
    public function pathUri(string|null $uri = null): string
    {
        $path = $uri ?? '';
        if (!$uri) {
            return $path;
        }
        $parse = parse_url($path);
        if (!is_array($parse)) {
            return $path;
        }
        $path = $parse['path'] ?? '';
        $query = $parse['query'] ?? '';
        if ($query !== '') {
            $path .= '?' . $query;
        }
        return $path;
    }

    /**
     * Create a path signature for the given URI
     *
     * @param string $random
     * @param int $timestamp
     * @param string|null $uri
     * @return string
     */
    public function createPathSignature(
        string $random,
        int $timestamp,
        string|null $uri = null
    ): string {
        $path = $this->pathUri($uri);
        return $this->hashOuter($path, $random, $timestamp);
    }

    /**
     * Create a browser signature for the given browser type
     *
     * @param string $random
     * @param int $timestamp
     * @param string|UserAgent|ClientBrowserType $browserType
     * @return string
     */
    public function createBrowserSignature(
        string $random,
        int $timestamp,
        string|UserAgent|ClientBrowserType $browserType
    ): string {
        if ($browserType instanceof UserAgent) {
            $browserType = $browserType->clientBrowserType;
        }
        if ($browserType instanceof ClientBrowserType) {
            $browserType = $browserType->getBrowserIdentity();
        }
        return $this->hashOuter($browserType, $random, $timestamp);
    }

    /**
     * Generate Authenticator Payload
     *
     * @param string|int|UserInterface $user if null, will use current user id
     * @param string|null $uri
     * @param DateInterval|Duration|null $duration
     * @return AuthenticatorPayload
     */
    public function generate(
        string|int|UserInterface $user,
        string|null $uri = null,
        DateInterval|Duration $duration = null
    ): AuthenticatorPayload {
        $userAgent = $this->getUserAgent();
        $user = $this->getUserFactory()->findOrEmpty($user);
        $random = Random::bytes(16);
        $userId = $user->getId();
        $timestamp = $this->getTimestampMillisecond();
        $expired = $this->createDuration($duration)->toDate()->toMilliSeconds();
        $user64 = pack('J', $userId);
        $timestamp64 = pack('J', $timestamp);
        $duration = pack('J', $expired);

        $pathSignature = $this->createPathSignature($random, $timestamp, $uri);
        $browserSignature = $this->createBrowserSignature($random, $timestamp, $userAgent);
        $idBinding = $this->hashInner($user64, $random, $user, $timestamp);
        $payload = $this->concat(
            $random,
            $pathSignature,
            $idBinding,
            $timestamp64,
            $duration,
            $user64,
            $browserSignature
        );
        $signature = $this->hashOuter($payload, $random, $timestamp);
        $token = bin2hex($this->concat($payload, $signature, $browserSignature));
        return new AuthenticatorPayload(
            $this,
            $user,
            $token,
            $payload,
            $signature,
            $browserSignature,
            $pathSignature,
            $random,
            $timestamp,
            $expired
        );
    }

    /**
     * Parse the given token and return an AuthenticatorPayload object if valid
     *
     * @param string|AuthenticatorPayload $token
     * @return AuthenticatorPayload|null
     */
    public function parse(string|AuthenticatorPayload $token): ?AuthenticatorPayload
    {
        $token = $token instanceof AuthenticatorPayload ? $token->token : $token;
        if (strlen($token) !== self::LENGTH) {
            return null;
        }

        $tokenBin = hex2bin($token);
        if ($tokenBin === false) {
            return null;
        }

        $browserSignature = substr($tokenBin, -32); // browser signature is the last 32 bytes
        $signature = substr($tokenBin, -64, 32); // signature is the second-to-last 32 bytes
        $payload = substr($tokenBin, 0, -64); // payload is the rest
        $random = substr($payload, 0, 16);
        $pathSignature = substr($payload, 16, 32);
        $comparedBrowserSignature = substr($payload, -32);
        // check browser signature first, if it doesn't match, return null
        if (!hash_equals($browserSignature, $comparedBrowserSignature)) {
            return null;
        }
        $idBinding = substr($payload, 48, 32);
        $ts64 = substr($payload, 80, 8);
        $duration = substr($payload, 88, 8);
        $u64 = substr($payload, 96, 8);
        $userId = unpack('J', $u64)[1] ?? null;
        if ($userId === null || $userId < 0) { // minimum user is 0
            return null;
        }
        $timestamp = unpack('J', $ts64)[1] ?? 0;
        $expired = unpack('J', $duration)[1] ?? 0;
        $tokenSign = $this->hashOuter($payload, $random, $timestamp);
        if (!hash_equals($signature, $tokenSign)) {
            return null;
        }
        $user = $this->getUserFactory()->findById($userId);
        if (!$user) {
            return null;
        }
        $idBindingSign = $this->hashInner($u64, $random, $user, $timestamp);
        if (!hash_equals($idBinding, $idBindingSign)) {
            return null;
        }
        return new AuthenticatorPayload(
            $this,
            $user,
            $token,
            $payload,
            $signature,
            $browserSignature,
            $pathSignature,
            $random,
            $timestamp,
            $expired
        );
    }
}
