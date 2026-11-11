<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging;

use ErrorException;
use InvalidArgumentException;
use JsonSerializable;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Stack;
use WP_Error;
use WpOrg\Requests\IdnaEncoder;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_unshift;
use function array_values;
use function base64_encode;
use function class_exists;
use function count;
use function do_action;
use function esc_url_raw;
use function explode;
use function function_exists;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function is_wp_error;
use function json_decode;
use function json_encode;
use function parse_url;
use function preg_match;
use function preg_replace;
use function property_exists;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function time;
use function trim;
use function ucwords;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use const E_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PHP_URL_HOST;

final class FirebaseMessagingEasy extends FirebaseMessagingUtil implements JsonSerializable
{

    public const string OK = 'OK';

    public const string INVALID_DATA = 'INVALID_DATA';

    public const string INVALID_TYPE = 'INVALID_TYPE';

    public const string INVALID_PROJECT_ID = 'INVALID_PROJECT_ID';

    public const string INVALID_PRIVATE_KEY_ID = 'INVALID_PRIVATE_KEY_ID';

    public const string INVALID_PRIVATE_KEY = 'INVALID_PRIVATE_KEY';

    public const string INVALID_CLIENT_EMAIL = 'INVALID_CLIENT_EMAIL';

    public const string INVALID_CLIENT_ID = 'INVALID_CLIENT_ID';

    public const string INVALID_AUTH_URI = 'INVALID_AUTH_URI';

    public const string INVALID_TOKEN_URI = 'INVALID_TOKEN_URI';

    public const string INVALID_AUTH_PROVIDER_X509_CERT_URL = 'INVALID_AUTH_PROVIDER_X509_CERT_URL';

    public const string INVALID_CLIENT_X509_CERT_URL = 'INVALID_CLIENT_X509_CERT_URL';

    public const string INVALID_UNIVERSE_DOMAIN = 'INVALID_UNIVERSE_DOMAIN';

    public const string ERROR_NOT_READY = 'ERROR_NOT_READY';

    public const string ERROR_INVALID_TOPIC = 'ERROR_INVALID_TOPIC';

    public const string ERROR_INVALID_TOKEN = 'ERROR_INVALID_TOKEN';

    public const string ERROR_INVALID_INPUT = 'ERROR_INVALID_INPUT';

    public const string ERROR_INVALID_RESPONSE = 'ERROR_INVALID_RESPONSE';

    public const string ERROR_INVALID_OPERATION = 'ERROR_INVALID_OPERATION';

    /**
     * @var array{
     *     type: string,
     *     project_id: string,
     *     private_key_id: string,
     *     private_key: string,
     *     client_email: string,
     *     client_id: string,
     *     auth_uri: string,
     *     token_uri: string,
     *     auth_provider_x509_cert_url: string,
     *     client_x509_cert_url: string,
     *     universe_domain: string,
     *     firebase_config: array<string, string>
     * }|array<string, mixed> $data
     */
    private array $data;

    /**
     * @var array<string, true>
     */
    private array $statuses;

    /**
     * @var string|WP_Error
     */
    private string|WP_Error $access_token;

    /**
     * @var InvalidArgumentException|null $initError
     */
    private ?InvalidArgumentException $initError = null;

    /**
     * @var int $timeout timeout in seconds for request
     */
    private int $timeout = self::TIMEOUT_SECONDS;

    /**
     * @var bool $is_default
     */
    private bool $is_default = false;

    /**
     * @var Site $site
     */
    public readonly Site $site;

    /**
     * @param mixed|array{
     *       type?: "service_account",
     *       project_id?: string,
     *       private_key_id?: string,
     *       private_key?: string,
     *       client_email?: string,
     *       client_id?: string,
     *       auth_uri?: "https://accounts.google.com/o/oauth2/auth",
     *       token_uri?: "https://oauth2.googleapis.com/token",
     *       auth_provider_x509_cert_url?: "https://www.googleapis.com/oauth2/v1/certs",
     *       client_x509_cert_url?: string,
     *       universe_domain: string,
     *       firebase_config: array{
     *           vapidPublicKey?: string,
     *           messagingSenderId?: string,
     *           authDomain?: string,
     *           appId?: string,
     *           apiKey?: string,
     *           projectId?: string
     *       }
     *   } $jsonData
     */
    public function __construct(mixed $jsonData, ?Site $site = null)
    {
        $this->site = $site ?? Stack::getInstance()->get(Site::class);
        if ($jsonData instanceof JsonSerializable) {
            $jsonData = $jsonData->jsonSerialize();
        }
        if (is_string($jsonData)) {
            $json = json_decode($jsonData, true);
            if (is_array($json)) {
                $jsonData = $json;
            }
        }
        if (!is_array($jsonData)) {
            $this->initError = new InvalidArgumentException('Invalid JSON data provided');
            $this->statuses[self::INVALID_DATA] = true;
            $jsonData = [];
        }
        $this->data = $jsonData;
        $this->data['type'] ??= self::TYPE_SERVICE_ACCOUNT;
        $this->data['auth_provider_x509_cert_url'] ??= self::AUTH_PROVIDER_X509_CERT_URL;
        $this->data['token_uri'] ??= self::TOKEN_URI;
        if (!is_string($this->data['universe_domain'] ?? null)) {
            $this->data['universe_domain'] = self::UNIVERSE_DOMAIN;
        }
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $min = 3;
        $max = 20;
        $timeout = min($timeout, $max);
        $timeout = max($timeout, $min);
        $this->timeout = $timeout;
    }

    public function isOk(): bool
    {
        return $this->hasValidCredentials() && $this->hasValidFrontendConfig();
    }

    public function hasValidCredentials(): bool
    {
        $statuses = $this->getStatuses();
        return ($statuses[0] ?? null) === self::OK && count($statuses) === 1;
    }

    public function hasValidFrontendConfig(): bool
    {
        $config = $this->getFiltered()['firebase_config'] ?? null;
        return $config && isset(
            $config['vapidPublicKey'],
            $config['messagingSenderId'],
            $config['authDomain'],
            $config['appId'],
            $config['apiKey']
        );
    }

    /**
     * @var array{
     *      type?: "service_account",
     *      project_id?: string,
     *      private_key_id?: string,
     *      private_key?: string,
     *      client_email?: string,
     *      client_id?: string,
     *      auth_uri?: "https://accounts.google.com/o/oauth2/auth",
     *      token_uri?: "https://oauth2.googleapis.com/token",
     *      auth_provider_x509_cert_url?: "https://www.googleapis.com/oauth2/v1/certs",
     *      client_x509_cert_url?: string,
     *      universe_domain: string,
     *      firebase_config: array{
     *          vapidPublicKey?: string,
     *          messagingSenderId?: string,
     *          authDomain?: string,
     *          appId?: string,
     *          apiKey?: string,
     *          projectId?: string
     *      }
     *  } $filtered
     */
    private array $filtered;

    /**
     * @return array{
     *      type?: "service_account",
     *      project_id?: string,
     *      private_key_id?: string,
     *      private_key?: string,
     *      client_email?: string,
     *      client_id?: string,
     *      auth_uri?: "https://accounts.google.com/o/oauth2/auth",
     *      token_uri?: "https://oauth2.googleapis.com/token",
     *      auth_provider_x509_cert_url?: "https://www.googleapis.com/oauth2/v1/certs",
     *      client_x509_cert_url?: string,
     *      universe_domain: string,
     *      firebase_config: array{
     *          vapidPublicKey?: string,
     *          messagingSenderId?: string,
     *          authDomain?: string,
     *          appId?: string,
     *          apiKey?: string,
     *          projectId?: string
     *      }
     *  }
     */
    public function getFiltered(): array
    {
        if (isset($this->filtered)) {
            return $this->filtered;
        }
        $filtered = [];
        $config = $this->data['firebase_config'] ?? [];
        $projectId = self::filterProjectId($this->data['project_id'] ?? null)
            ?? self::filterProjectId($config['projectId'] ?? null);
        /**
         * @var array{
         *      vapidPublicKey?: string,
         *      messagingSenderId?: string,
         *      authDomain?: string,
         *      appId?: string,
         *      apiKey?: string,
         *      projectId?: string
         *  } $frontEndConfig
         */
        $frontEndConfig = [];
        if (is_array($config) && !empty($config)) {
            $frontEndConfig = array_filter([
                'vapidPublicKey' => self::filterVapIDPublicKey($config['vapidPublicKey'] ?? null),
                'messagingSenderId' => self::filterSenderId($config['messagingSenderId'] ?? null),
                'authDomain' => self::filterAuthDomain($config['authDomain'] ?? null),
                'appId' => self::filterAppId($config['appId'] ?? null),
                'apiKey' => self::filterAPIKey($config['apiKey'] ?? null),
                'projectId' => $projectId
            ]);
        }
        $config = [
            "type" => self::TYPE_SERVICE_ACCOUNT,
            "project_id" => $projectId,
            "private_key_id" => self::filterPrivateKeyId($this->data['private_key_id'] ?? null),
            "private_key" => self::filterPrivateKey($this->data['private_key'] ?? null),
            "client_email" => self::filterClientEmail($this->data['client_email'] ?? null),
            "client_id" => self::filterClientId($this->data['client_id'] ?? null),
            "auth_uri" => self::AUTH_URI,
            "token_uri" => self::TOKEN_URI,
            "auth_provider_x509_cert_url" => self::AUTH_PROVIDER_X509_CERT_URL,
            "client_x509_cert_url" => self::filterClientX509CertUrl($this->data['client_x509_cert_url'] ?? null),
            "universe_domain" => 'googleapis.com', // fixed
        ];
        $filtered = array_filter($config);
        $filtered['firebase_config'] = array_filter($frontEndConfig);
        return $this->filtered = $filtered;
    }

    /**
     * @return string[]
     */
    public function getStatuses(): array
    {
        if (!empty($this->statuses)) {
            return array_keys($this->statuses);
        }
        $filtered = $this->getFiltered();
        $dataCheck = [
            'type' => self::INVALID_TYPE,
            'project_id' => self::INVALID_PROJECT_ID,
            'private_key_id' => self::INVALID_PRIVATE_KEY_ID,
            'private_key' => self::INVALID_PRIVATE_KEY,
            'auth_uri' => self::INVALID_AUTH_URI,
            'token_uri' => self::INVALID_TOKEN_URI,
            'auth_provider_x509_cert_url' => self::INVALID_AUTH_PROVIDER_X509_CERT_URL,
        ];

        $this->statuses = [];
        foreach ($dataCheck as $key => $status) {
            if (!isset($filtered[$key])) {
                $this->statuses[$status] = true;
                continue;
            }
            $this->data[$key] = $filtered[$key];
        }
        $project_id = $filtered['project_id'] ?? null;
        $clientEmail = $project_id ? self::filterClientEmail($filtered['client_email'] ?? null, $project_id) : null;
        $client_x509_url = $clientEmail ? self::filterClientX509CertUrl(
            $filtered['client_x509_cert_url'] ?? null,
            $clientEmail
        ) : null;
        if (!$clientEmail) {
            $this->statuses[self::INVALID_CLIENT_EMAIL] = true;
        }
        if (!$client_x509_url) {
            $this->statuses[self::INVALID_CLIENT_X509_CERT_URL] = true;
        }
        if (empty($this->statuses)) {
            $this->statuses = [
                self::OK => true
            ];
        }
        return array_keys($this->statuses);
    }

    /**
     * @return array{
     *      vapidPublicKey?: string,
     *      messagingSenderId?: string,
     *      authDomain?: string,
     *      appId?: string,
     *      apiKey?: string,
     *      projectId?: string
     *  }
     */
    public function getFrontendConfig(): array
    {
        return $this->getFiltered()['firebase_config'] ?? [];
    }

    public function statusContain(string $key): bool
    {
        return isset($this->getStatuses()[$key]);
    }

    public function getInitError(): ?InvalidArgumentException
    {
        return $this->initError;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getType(): ?string
    {
        return $this->getFiltered()['type'] ?? null;
    }

    public function getProjectId(): ?string
    {
        return $this->getFiltered()['project_id'] ?? null;
    }

    public function getPrivateKeyId(): ?string
    {
        return $this->getFiltered()['private_key_id'] ?? null;
    }

    public function getPrivateKey(): ?string
    {
        return $this->getFiltered()['private_key'] ?? null;
    }

    public function getClientEmail(): ?string
    {
        return $this->getFiltered()['client_email'] ?? null;
    }

    public function getClientId(): ?string
    {
        return $this->getFiltered()['client_id'] ?? null;
    }

    public function getAuthUri(): ?string
    {
        return $this->getFiltered()['auth_uri'] ?? null;
    }

    public function getTokenUri(): ?string
    {
        return $this->getFiltered()['token_uri'] ?? null;
    }

    public function getAuthProviderX509CertUrl(): ?string
    {
        return $this->getFiltered()['auth_provider_x509_cert_url'] ?? null;
    }

    public function getClientX509CertUrl(): ?string
    {
        return $this->getFiltered()['client_x509_cert_url'] ?? null;
    }

    public function getUniverseDomain(): string
    {
        return $this->getFiltered()['universe_domain'] ?? '';
    }

    public function getApiKey(): ?string
    {
        return ($this->getWebConfig() ?? [])['apiKey'] ?? null;
    }

    public function getVapidPublicKey(): ?string
    {
        return ($this->getWebConfig() ?? [])['vapidPublicKey'] ?? null;
    }

    public function getAuthDomain(): ?string
    {
        return ($this->getWebConfig() ?? [])['authDomain'] ?? null;
    }

    public function getMessagingSenderId(): ?string
    {
        return ($this->getWebConfig() ?? [])['messagingSenderId'] ?? null;
    }

    public function getAppID(): ?string
    {
        return ($this->getWebConfig() ?? [])['appId'] ?? null;
    }

    public function getAppIdType(): ?string
    {
        $app_id = $this->getAppID();
        if (!$app_id) {
            return null;
        }
        $parsed = self::parseAppId($app_id);
        return $parsed ? $parsed['platform'] : null;
    }

    /**
     * @param mixed $token
     * @param string $topic
     * @param string ...$topics
     * @return WP_Error|array{
     *     token:string,
     *     topics:string[],
     *     topics:string[],
     * }
     */
    public function subscribeTokenToTopic(mixed $token, string $topic, string ...$topics): array|WP_Error
    {
        if (!$this->isOk()) {
            return new WP_Error('not_ready', 'FCM credentials not ready');
        }

        $access_token = $this->getAccessToken();
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        array_unshift($topics, $topic);
        $token = trim((string)$token);
        if ($token === '') {
            return new WP_Error('invalid_param', 'Invalid token, token is empty');
        }

        $valid_topics = [];
        foreach ($topics as $t) {
            $filtered = $this->filterTopic($t);
            if ($filtered) {
                $valid_topics[$filtered] = $filtered;
            }
        }

        if (empty($valid_topics)) {
            return new WP_Error('invalid_param', 'No valid topics provided');
        }

        $url = 'https://iid.googleapis.com/iid/v1:batchAdd';
        $results = [];
        $valid_topics = array_values($valid_topics);
        $valid_topics = array_slice($valid_topics, 0, 10); // limit to 10
        foreach ($valid_topics as $topic) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'access_token_auth' => 'true'
                ],
                'body' => json_encode([
                    'to' => '/topics/' . $topic,
                    'registration_tokens' => [$token]
                ]),
                'timeout' => $this->getTimeout()
            ]);

            if (is_wp_error($response)) {
                $results[$topic] = [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code !== 200) {
                $results[$topic] = [
                    'success' => false,
                    'status_code' => $status_code,
                    'response' => $body
                ];
            } else {
                $results[$topic] = array_merge(['success' => true], is_array($body) ? $body : []);
            }
        }
        return [
            'token' => $token,
            'topics' => $valid_topics,
            'results' => $results
        ];
    }

    /**
     * @param string $device_token_or_topic
     * @param string|mixed $title
     * @param string|mixed $body
     * @param string|mixed $link
     * @param mixed|null $image_uri
     * @param mixed|null $icon_uri
     * @param mixed|null $badge
     * @param array<string, mixed> $data additional data
     * @param MessagingPriority ...$messagingPriority
     * @return WP_Error|array{
     *     notification?: array{
     *          title?: string,
     *          body?: string,
     *          image?: string,
     *          badge?: string,
     *          icon?: string,
     *          tag?: string,
     *     },
     *     webpush?: array{
     *         notification: array{
     *          title?: string,
     *          body?: string,
     *          image?: string,
     *          badge?: string,
     *          icon?: string,
     *          tag?: string,
     *         },
     *          fcm_options: array{
     *              link?: string
     *          }
     *     },
     *     condition?: string,
     *     data?: array{
     *         topic_source?: string
     *     }
     * }
     */
    public function createParams(
        mixed $device_token_or_topic,
        mixed $title = null,
        mixed $body = null,
        mixed $link = null,
        mixed $tag = null,
        mixed $image_uri = null,
        mixed $icon_uri = null,
        mixed $badge = null,
        array $data = [],
        MessagingPriority ...$messagingPriority
    ): array|WP_Error {
        if (!$this->isOk()) {
            return new WP_Error(
                self::ERROR_NOT_READY,
                'Google FCM credentials data has invalid'
            );
        }
        $params = [];
        $paramsData = $data;
        unset($paramsData['topics'], $paramsData['tag']);
        if (is_string($device_token_or_topic) && str_contains($device_token_or_topic, ':')) {
            $token = self::filterWebToken($device_token_or_topic);
            if (!$token) {
                return new WP_Error(
                    self::ERROR_INVALID_TOKEN,
                    'The device token is invalid'
                );
            }
            $params['token'] = $token;
        } else {
            $topic = is_string($device_token_or_topic) ? [$device_token_or_topic] : $device_token_or_topic;
            if (!is_array($topic)) {
                return new WP_Error(
                    self::ERROR_INVALID_TOPIC,
                    'The topics are invalid'
                );
            }
            $topics = array_filter($topic, 'is_string');
            $topics = array_unique($topics);
            if (empty($topics)) {
                return new WP_Error(
                    self::ERROR_INVALID_TOPIC,
                    'The topic are empty'
                );
            }
            $topics = array_values($topics);
            $new_topics = array_map([self::class, 'filterTopic'], $topics);
            $new_topics = array_filter($new_topics, 'is_string');
            if (count($topics) !== count($new_topics)) {
                return new WP_Error(
                    self::ERROR_INVALID_TOPIC,
                    'The topic length are mismatched'
                );
            }
            if (count($new_topics) > 10) {
                return new WP_Error(
                    self::ERROR_INVALID_TOPIC,
                    'Too many topics'
                );
            }
            if (count($new_topics) === 1) {
                $params['topic'] = $new_topics[0];
                $target_source = [$new_topics[0]];
            } else {
                $conditions = array_map(function ($t) {
                    return "'" . addslashes($t) . "' in topics";
                }, $new_topics);

                $params['condition'] = implode(' || ', $conditions);
                $target_source = $new_topics;
            }
            $paramsData['topics'] = implode(',', $target_source);
        }
        $platforms = $data['platforms'] ?? $data['platform'] ?? null;
        unset($data['platforms'], $data['platform']);
        if (is_string($platforms)) {
            $platforms = [$platforms];
        } elseif (is_array($platforms)) {
            $platforms = array_filter($platforms, 'is_string');
        } else {
            $platforms = ['all'];
        }
        $platforms = array_map(static fn($e) => strtolower(trim($e)), $platforms);
        $platforms = in_array('all', $platforms)
            ? ['all']
            : array_unique(array_filter($platforms, static fn($e) => isset(self::AVAILABLE_DEVICE_PLATFORMS[$e])));
        $paramsData['platforms'] = implode(',', $platforms);
        foreach ($paramsData as $key => $item) {
            if (!is_string($key) || !is_scalar($item)) {
                unset($paramsData[$key]);
                continue;
            }
            if (is_bool($item)) {
                $item = $item ? 'true' : 'false';
            } elseif (is_numeric($item)) {
                $item = (string)$item;
            }
            $paramsData[$key] = $item;
        }
        $title = self::filterTitle($title);
        $body = self::filterBody($body);
        if (!$title && !$body) {
            return new WP_Error(
                self::ERROR_INVALID_INPUT,
                'The title or body is invalid'
            );
        }
        $tag = self::filterTag($tag);
        $link = self::filterLink($link);
        $notification = [];
        $webpush_options = [];
        $webpush_notif = [];
        if ($title) {
            $notification['title'] = $title;
        }
        if ($body) {
            $notification['body'] = $body;
        }
        if (!empty($image_uri) && is_string($image_uri) && preg_match('~\.(jpe?g|png|avif)([?#].*)?$~i', $image_uri)) {
            $notification['image'] = esc_url_raw($image_uri);
        }
        if ($link) {
            $webpush_options['fcm_options']['link'] = esc_url_raw($link);
        }
        if (!empty($badge) && is_string($badge) && preg_match('~\.(jpe?g|png|avif)([?#].*)?$~i', $badge)) {
            $webpush_notif['badge'] = esc_url_raw($badge);
        }
        if (!empty($icon_uri) && is_string($icon_uri) && preg_match('~\.(jpe?g|png|avif)([?#].*)?$~i', $icon_uri)) {
            $webpush_notif['icon'] = esc_url_raw($icon_uri);
        }
        if ($tag) {
            $webpush_notif['tag'] = $tag;
            $paramsData['tag'] = $tag;
        }

        if (!empty($notification)) {
            $params['notification'] = $notification;
        }

        if (!empty($webpush_notif)) {
            $webpush_options['notification'] = $webpush_notif;
        }

        if (!empty($webpush_options)) {
            $params['webpush'] = $webpush_options;
        }
        if (!empty($messagingPriority)) {
            foreach ($messagingPriority as $priorityObj) {
                if ($priorityObj->isValid()) {
                    $priorityParams = $priorityObj->toParams();
                    if (is_array($priorityParams)) {
                        $params = array_merge_recursive($params, $priorityParams);
                    }
                }
            }
        }
        $params['data'] = $paramsData;
        return [
            'message' => $params
        ];
    }

    public function getAccessToken(bool $force = false, bool $reuse = true): string|WP_Error
    {
        if (!isset($this->access_token) || $force) {
            $this->access_token = $this->fetchAccessToken($force, $reuse);
        }
        return $this->access_token;
    }

    public function getPushMessageURL(): ?string
    {
        $project_id = $this->getProjectId();
        if (!is_string($project_id)) {
            return null;
        }
        return sprintf("https://fcm.googleapis.com/v1/projects/%s/messages:send", $project_id);
    }

    /**
     * @return ?array{
     *      vapidPublicKey?: string,
     *      messagingSenderId?: string,
     *      authDomain?: string,
     *      appId?: string,
     *      apiKey?: string,
     *      projectId?: string
     *  }
     */
    public function getWebConfig(): ?array
    {
        if (!$this->hasValidFrontendConfig()) {
            return null;
        }
        return $this->getFrontendConfig();
    }

    /**
     * @return ?array{
     *      type?: "service_account",
     *      project_id?: string,
     *      private_key_id?: string,
     *      private_key?: string,
     *      client_email?: string,
     *      client_id?: string,
     *      auth_uri?: "https://accounts.google.com/o/oauth2/auth",
     *      token_uri?: "https://oauth2.googleapis.com/token",
     *      auth_provider_x509_cert_url?: "https://www.googleapis.com/oauth2/v1/certs",
     *      client_x509_cert_url?: string,
     *      universe_domain: string
     *  }
     */
    public function getCredentials(): ?array
    {
        if (!$this->hasValidCredentials()) {
            return null;
        }
        $config = $this->getFiltered();
        unset($config['firebase_config']);
        return $config;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function allConfig(): ?array
    {
        if (!$this->isOk()) {
            return null;
        }
        return $this->getFiltered();
    }

    /**
     * @var self $instance
     */
    private static self $instance;

    public static function default(bool $force = false, ?Site $site = null): self
    {
        $site ??= Stack::getInstance()->get(Site::class);
        if ($force) {
            self::$instance = new self($site->options->get(self::SETTING_NAME), $site);
            do_action('traydigita_firebase_messaging_force', self::$instance);
        } else {
            self::$instance ??= new self($site->options->get(self::SETTING_NAME), $site);
        }
        self::$instance->is_default = true;
        return self::$instance;
    }

    public static function normalizeTopicName(string $topic): string
    {
        $topic = trim($topic);
        $topic = str_replace('_', ' ', $topic);
        return ucwords($topic);
    }

    /**
     * @param string $topics
     * @param Site|null $site
     * @return string[]
     */
    public static function parseTopics(string $topics, ?Site $site = null): array
    {
        $topic_settings = trim($topics);
        $topic_settings = explode(',', $topic_settings);
        $topic_settings = array_map([self::class, 'filterTopic'], $topic_settings);
        $topic_settings = [self::siteTopic($site), ...array_filter($topic_settings)];
        $topics = array_values(array_unique($topic_settings));
        return array_slice($topics, 0, 10);
    }

    public function __get(string $name) : mixed
    {
        return property_exists(
            $this,
            $name
        ) ? $this->$name : null;
    }

    public function __set(string $name, mixed $value): void
    {
        // void
    }

    /**
     * @param Site|null $site
     * @return string
     */
    public static function siteTopic(?Site $site = null): string
    {
        $site_url = ($site??Stack::getInstance()->getSite())->siteUrl;
        $host = parse_url($site_url, PHP_URL_HOST);
        if (!$host) {
            preg_match('~^https?://([^/]+)(/|$)~', $site_url, $match);
            $host = $match[1] ?? ($_SERVER['HTTP_HOST'] ?? '') ?: '';
        }
        $site_host = strtolower($host);
        if (str_starts_with($site_host, 'www.')) {
            $site_host = substr($site_host, 4);
        }
        if (class_exists(IdnaEncoder::class)) {
            try {
                $site_host = IdnaEncoder::encode($site_host);
            } catch (Throwable) {
            }
        } elseif (function_exists('idn_to_ascii')) {
            $site_host = idn_to_ascii($site_host, IDNA_NONTRANSITIONAL_TO_ASCII) ?: $site_host;
        }

        $topic = str_replace('.', '_', $site_host);
        $topic = preg_replace('/[^a-zA-Z0-9-_]/', '_', $topic);
        $topic = preg_replace('/_+/', '_', (string)$topic);

        return trim((string)$topic, '_');
    }

    /**
     * @param mixed $device_token_or_topic
     * @param mixed|null $title
     * @param mixed|null $body
     * @param mixed|null $link
     * @param mixed|null $tag
     * @param mixed|null $image_uri
     * @param mixed|null $icon_uri
     * @param mixed|null $badge
     * @param array<string, mixed> $data aditional data
     * @param MessagingPriority ...$messagingPriority
     * @return array{
     *     success: bool,
     *     params: array<array-key, mixed>,
     *     response: array<array-key, mixed>
     * }|WP_Error
     */
    public function sendMessage(
        mixed $device_token_or_topic,
        mixed $title = null,
        mixed $body = null,
        mixed $link = null,
        mixed $tag = null,
        mixed $image_uri = null,
        mixed $icon_uri = null,
        mixed $badge = null,
        array $data = [],
        MessagingPriority ...$messagingPriority
    ): array|WP_Error {
        $params = $this->createParams(
            $device_token_or_topic,
            $title,
            $body,
            $link,
            $tag,
            $image_uri,
            $icon_uri,
            $badge,
            $data,
            ...$messagingPriority
        );
        if (!is_array($params)) {
            return $params;
        }
        return $this->sendDirect($params);
    }

    /**
     * @template TParams of array<array-key, mixed>
     * @param TParams $params
     * @param int|null $timeout
     * @return WP_Error|array{
     *     success: bool,
     *     params: TParams,
     *     response: array<array-key, mixed>
     * }
     */
    public function sendDirect(array $params, ?int $timeout = null): array|WP_Error
    {
        $fcm_url = $this->getPushMessageURL();
        if (!$fcm_url || !$this->isOk()) {
            return new WP_Error(
                self::ERROR_NOT_READY,
                'Google FCM credentials data has invalid'
            );
        }
        $access_token = $this->getAccessToken();
        if (!is_string($access_token)) {
            return $access_token;
        }
        $timeout ??= $this->getTimeout();
        $response = wp_remote_post($fcm_url, [
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $access_token),
                'Content-Type' => 'application/json; UTF-8',
            ],
            'body' => json_encode($params, JSON_UNESCAPED_SLASHES),
            'timeout' => $timeout,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $response = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($response)) {
            return [
                'success' => $code >= 200 && $code < 300 && is_string($response['name'] ?? null),
                'params' => $params,
                'response' => $response
            ];
        }
        return new WP_Error(
            self::ERROR_INVALID_RESPONSE,
            'Response push message is not valid'
        );
    }

    /**
     * Fetch the access token
     *
     * @param bool $force
     * @param bool $reuse
     * @return string|WP_Error
     */
    private function fetchAccessToken(bool $force = false, bool $reuse = true): string|WP_Error
    {
        if (!function_exists('openssl_sign')) {
            return new WP_Error(
                self::ERROR_NOT_READY,
                'OpenSSL Library has not found'
            );
        }

        if (!$this->isOk()) {
            return new WP_Error(
                self::ERROR_NOT_READY,
                'Google FCM credentials data has invalid'
            );
        }
        $clientEmail = $this->getClientEmail();
        $privateKey = $this->getPrivateKey();
        if (!$clientEmail || !$privateKey) {
            return new WP_Error(
                self::ERROR_NOT_READY,
                'Google FCM credentials data has invalid'
            );
        }
        $cacheKey = sprintf('fcm_token_%s', sha1($clientEmail . '::' . $privateKey));
        $dataToken = $this->site->transients->get($cacheKey);
        $the_token = null;
        // 30 seconds is spare
        if (is_array($dataToken)
            && is_int($dataToken['expires_in'])
            && is_int($dataToken['timestamp'])
            && is_string($dataToken['token'])
            && (($dataToken['timestamp'] + $dataToken['expires_in'] - 30) > time())
        ) {
            if (!$force) {
                return $dataToken['token'];
            }
            $the_token = $dataToken['token'];
        }
        $header = (string)json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES);
        $now = time();
        $claim_set = (string)json_encode([
            'iss' => $clientEmail,
            'scope' => self::MESSAGING_URI,
            'aud' => self::TOKEN_URI,
            'exp' => $now + 3600,
            'iat' => $now
        ], JSON_UNESCAPED_SLASHES);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim_set));
        try {
            set_error_handler(static function (int $errNo, string $message, string $errFile, int $errLine) {
                throw new ErrorException($message, $errNo, E_ERROR, $errFile, $errLine);
            });
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            $signatureResult = \openssl_sign(
                $base64UrlHeader . "." . $base64UrlClaim,
                $signature,
                $privateKey,
                'SHA256'
            );
        } catch (Throwable $e) {
            return new WP_Error(
                self::ERROR_INVALID_OPERATION,
                $e->getMessage()
            );
        } finally {
            restore_error_handler();
        }
        if (!$signatureResult) {
            return new WP_Error(
                self::ERROR_INVALID_OPERATION,
                'OpenSSL Library can not signing private key'
            );
        }
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        $jwt = $base64UrlHeader . "." . $base64UrlClaim . "." . $base64UrlSignature;
        $response = wp_remote_post(self::TOKEN_URI, [
            'body' => [
                'grant_type' => self::GRANT_TYPE,
                'assertion' => $jwt
            ],
            'timeout' => $this->getTimeout()
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $access_token = is_array($body) ? ($body['access_token'] ?? null) : null;
        if ($the_token && $reuse) {
            return $the_token;
        }
        if (is_string($access_token)
            && is_int($body['expires_in'] ?? null)
            && ($body['expires_in'] - 30) > 0
        ) {
            $this->site->transients->set($cacheKey, [
                'token' => $access_token,
                'expires_in' => $body['expires_in'],
                'timestamp' => time()
            ], $body['expires_in'] - 30);
        }
        return is_string($access_token) ? $access_token : new WP_Error(
            self::ERROR_INVALID_RESPONSE,
            'Invalid access token response'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->allConfig();
    }
}
