<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging;

use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function rawurldecode;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

class FirebaseMessagingUtil
{
    public const string SETTING_NAME = 'google_fcm_credentials';

    public const string TYPE_SERVICE_ACCOUNT = 'service_account';

    public const string GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    public const int TIMEOUT_SECONDS = 5;

    public const string AUTH_URI = 'https://accounts.google.com/o/oauth2/auth';

    public const string MESSAGING_URI = 'https://www.googleapis.com/auth/firebase.messaging';

    public const string TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public const string AUTH_PROVIDER_X509_CERT_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    public const string CLIENT_X509_CERT_URI = 'https://www.googleapis.com/robot/v1/metadata/x509/';

    public const string UNIVERSE_DOMAIN = 'googleapis.com';

    public const int MIN_LENGTH_CLIENT_ID = 10;

    public const int MAX_LENGTH_CLIENT_ID = 24;

    // PLATFORM
    public const string PLATFORM_ALL = 'All';

    public const string PLATFORM_MOBILE = 'Mobile';

    public const string PLATFORM_DESKTOP = 'Desktop';

    public const string PLATFORM_WEB = 'Web';

    public const string PLATFORM_ANDROID = 'Android';

    public const string PLATFORM_IOS = 'iOS';

    public const string PLATFORM_MACOS = 'macOS';

    public const string PLATFORM_LINUX = 'Linux';

    public const string PLATFORM_WINDOWS = 'Windows';

    /**
     * @var array<string, string> $AVAILABLE_DEVICE_PLATFORMS
     */
    public const array AVAILABLE_DEVICE_PLATFORMS = [
        'all' => self::PLATFORM_ALL,
        'mobile' => self::PLATFORM_MOBILE,
        'desktop' => self::PLATFORM_DESKTOP,
        'android' => self::PLATFORM_ANDROID,
        'ios' => self::PLATFORM_IOS,
        'macos' => self::PLATFORM_MACOS,
        'linux' => self::PLATFORM_LINUX,
        'windows' => self::PLATFORM_WINDOWS,
        'web' => self::PLATFORM_WEB,
    ];

    /**
     * Filters and validates the sender ID.
     *
     * @param mixed $id The sender ID to filter.
     * @return numeric-string|null The filtered sender ID if valid, or null if invalid.
     */
    public static function filterSenderId(mixed $id): ?string
    {
        if (!is_numeric($id)) {
            return null;
        }
        $id = trim((string)$id);
        /**
         * @var numeric-string $id
         */
        return preg_match('~^[0-9]{6,15}$~', $id) ? $id : null;
    }

    /**
     * @param mixed $id
     * @return ?array{
     *     id: non-empty-string,
     *     schema: string,
     *     project_number: string,
     *     platform: "web"|"android"|"ios"|"windows",
     *     hash: string,
     *}
     */
    public static function parseAppId(mixed $id): ?array
    {
        if (!is_string($id)) {
            return null;
        }
        $id = trim($id);
        preg_match(
            '~^(?P<schema>[12]):(?P<project_number>[0-9]{6,16}):(?P<platform>android|ios|web|windows):(?P<hash>[a-f0-9]{5,40})$~i', // phpcs:ignore
            $id,
            $match
        );
        if (!$match) {
            return null;
        }
        /**
         * @var array{
         *     schema: string,
         *     project_number: string,
         *     platform: "web"|"android"|"ios"|"windows",
         *     hash: string,
         * } $match
         */
        $match['platform'] = strtolower($match['platform']);
        return [
            'id' => sprintf(
                '%s:%s:%s:%s',
                $match['schema'],
                $match['project_number'],
                $match['platform'],
                strtolower($match['hash'])
            ),
            'schema' => $match['schema'],
            'project_number' => $match['project_number'],
            'platform' => $match['platform'],
            'hash' => $match['hash'],
        ];
    }

    /**
     * Filters and validates the app ID.
     *
     * @param mixed $id The app ID to filter.
     * @return string|null The filtered app ID if valid, or null if invalid.
     */
    public static function filterAppId(mixed $id): ?string
    {
        $app = self::parseAppId($id);
        return $app ? $app['id'] : null;
    }

    public static function filterAPIKey(mixed $apiKey): ?string
    {
        if (!is_string($apiKey)) {
            return null;
        }
        $apiKey = trim($apiKey);
        if (preg_match('~^AIza[0-9A-Za-z_-]{35}$~', $apiKey)) {
            return $apiKey;
        }
        return null;
    }

    public static function filterVapIDPublicKey(mixed $id): ?string
    {
        if (!is_string($id)) {
            return null;
        }
        $id = trim($id);
        if (strlen($id) !== 87) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9\-_]{87}$/', $id)) {
            return null;
        }
        return $id;
    }

    public static function filterAuthDomain(mixed $domain): ?string
    {
        if (!is_string($domain)) {
            return null;
        }
        $domain = trim($domain);
        if (strlen($domain) < 2 || strlen($domain) > 253) {
            return null;
        }
        if (!preg_match('/^([a-zA-Z0-9][-a-zA-Z0-9]{0,62})(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+$/', $domain)) {
            return null;
        }
        return strtolower($domain);
    }

    public static function filterStorageBucket(mixed $bucket): ?string
    {
        return self::filterAuthDomain($bucket);
    }

    /**
     * Filters and validates the project ID.
     *
     * @param mixed $id The project ID to filter.
     * @return string|null The filtered project ID if valid, or null if invalid.
     */
    public static function filterProjectId(mixed $id): ?string
    {
        if (!is_string($id)) {
            return null;
        }
        $id = strtolower(trim($id));
        return preg_match('~^[a-z][a-z0-9\-]{4,}[a-z0-9]$~', $id) ? $id : null;
    }

    public static function filterPrivateKeyId(mixed $data): ?string
    {
        if (!is_string($data)) {
            return null;
        }
        $data = trim($data);
        if (strlen($data) !== 40) {
            return null;
        }
        $data = strtolower($data);
        return !preg_match('~[^a-f0-9]~', $data) ? $data : null;
    }

    public static function filterPrivateKey(mixed $data): ?string
    {
        if (!is_string($data)) {
            return null;
        }
        $cleanPrivateKey = $data ? trim(str_replace(["\r\n", "\n\n"], "\n", $data)) : null;
        $length = $cleanPrivateKey ? strlen($cleanPrivateKey) : 0;
        $maxLength = 8192;
        $minLength = 1024;
        if (!$cleanPrivateKey
            || !preg_match(
                '/^-----BEGIN PRIVATE KEY-----\s+(.+)\s+-----END PRIVATE KEY-----$/s',
                $cleanPrivateKey,
                $matches
            )
            || empty($matches[1])
            || $length < $minLength
            || $length > $maxLength
            || !preg_match('~^[A-Za-z0-9+/]+={0,3}$~', str_replace("\n", "", trim($matches[1])))
        ) {
            return null;
        }
        return $cleanPrivateKey;
    }

    public static function filterClientEmail(mixed $data, ?string $project_id = null): ?string
    {
        if (!is_string($data)) {
            return null;
        }
        $data = strtolower(trim($data));
        if ($project_id) {
            $project_id = self::filterProjectId($project_id);
            if (!$project_id) {
                return null;
            }
            if (!str_ends_with($data, "@$project_id.iam.gserviceaccount.com")) {
                return null;
            }
        }
        if (!preg_match('~^firebase-adminsdk-[a-z0-9\-]+@.+\.iam\.gserviceaccount\.com$~', $data)) {
            return null;
        }
        return $data;
    }

    public static function filterClientId(mixed $data): ?string
    {
        if (!is_numeric($data)) {
            return null;
        }
        $data = trim((string) $data);
        $length = strlen($data);
        if ($length < self::MIN_LENGTH_CLIENT_ID
            || $length > self::MAX_LENGTH_CLIENT_ID
            || str_contains($data, '.')
        ) {
            return null;
        }
        return !preg_match('~[^0-9]~', $data) ? $data : null;
    }

    public static function filterClientX509CertUrl(mixed $uri, ?string $email = null): ?string
    {
        if (!is_string($uri)) {
            return null;
        }
        $uri = strtolower(rawurldecode(trim($uri)));
        if (!str_starts_with($uri, self::CLIENT_X509_CERT_URI)) {
            return null;
        }
        $clientEmail = trim(substr($uri, strlen(self::CLIENT_X509_CERT_URI)), '/');
        if (str_contains($clientEmail, '?') || str_contains($clientEmail, '#')) {
            $clientEmail = preg_replace('~^([^?#]+)[?#].*$~', '$1', $clientEmail);
        }
        if (($email && $email !== $clientEmail) || !self::filterClientEmail($clientEmail)) {
            return null;
        }
        return $uri;
    }

    public static function filterTopic(mixed $deviceTopic): ?string
    {
        if (!is_string($deviceTopic)) {
            return null;
        }
        $deviceTopic = trim($deviceTopic);
        if ($deviceTopic === '') {
            return null;
        }
        $deviceTopic = strtolower($deviceTopic);
        if (str_starts_with($deviceTopic, '/topics/')) {
            $deviceTopic = substr($deviceTopic, 8);
        }
        $deviceTopic = preg_replace('/[^a-zA-Z0-9-_.~%]/', '_', $deviceTopic);
        $deviceTopic = preg_replace('/_+/', '_', (string) $deviceTopic);
        $deviceTopic = trim((string) $deviceTopic, '_-.~%');
        $length = strlen($deviceTopic);
        if ($length < 1 || $length > 900) {
            return null;
        }

        return $deviceTopic;
    }

    public static function filterTitle(mixed $title): ?string
    {
        if (!is_string($title)) {
            return null;
        }
        $title = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $title));
        if (!$title) {
            return null;
        }
        $length = mb_strlen($title, 'UTF-8');

        if ($length < 1 || $length > 100) {
            return null;
        }

        return $title;
    }

    public static function filterTag(mixed $tag): ?string
    {
        if (!is_string($tag)) {
            return null;
        }

        $tag = preg_replace('/[^a-zA-Z0-9_\-.]/', '', trim($tag));
        if (!$tag) {
            return null;
        }
        $length = strlen($tag);
        if ($length < 1 || $length > 50) {
            return null;
        }
        return $tag;
    }

    public static function filterBody(mixed $body): ?string
    {
        if (!is_string($body)) {
            return null;
        }
        $body = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $body));
        if ($body === '') {
            return null;
        }
        $length = mb_strlen($body, 'UTF-8');
        if ($length > 500) {
            $body = mb_substr($body, 0, 497, 'UTF-8') . '...';
        }

        return $body;
    }

    public static function filterLink(mixed $link): ?string
    {
        if (!is_string($link)) {
            return null;
        }
        if (!preg_match('~^https?://[^/]+(/.*)?$~', $link)) {
            return null;
        }
        return $link;
    }

    public static function filterWebToken(mixed $token): ?string
    {
        if (!is_string($token)) {
            return null;
        }
        $token = trim($token);
        $length = strlen($token);
        if ($length < 100 || $length > 500 || !str_contains($token, ':')) {
            return null;
        }
        if (preg_match('~^[a-zA-Z0-9\-_]+:[a-zA-Z0-9\-_/+=]+$~', $token)) {
            return $token;
        }
        return null;
    }

    public static function filterMeasurementId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if (strlen($value) < 1 || strlen($value) > 22) {
            return null;
        }
        $value = strtoupper($value);
        // G-1SGX9N9WFC
        return preg_match('/^[A-Z]{1,3}-[A-Z0-9]{10,20}$/', $value) ? $value : null;
    }

    /**
     * Filters and validates the version string.
     *
     * @param mixed $value The version string to filter.
     * @return numeric-string|null The filtered version string if valid, or null if invalid.
     */
    public static function filterVersion(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (!is_numeric($value)) {
            return null;
        }
        $value = (string) $value;
        return preg_match('~^[1-9][0-9]?(\.[0-9]+)?$~', $value) ? $value : null;
    }

    public static function filterPackageName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if (strlen($value) < 1 || strlen($value) > 155) {
            return null;
        }
        return preg_match(
            '~^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$~',
            $value
        ) ? $value : null;
    }
}
