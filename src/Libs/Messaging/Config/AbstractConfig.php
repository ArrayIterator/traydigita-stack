<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Config;

use TrayDigita\WP\Stack\Source\Libs\Messaging\FirebaseMessagingUtil;
use function is_array;
use function is_scalar;
use function is_string;
use function property_exists;
use function strtolower;

abstract class AbstractConfig implements ConfigInterface
{
    protected mixed $originalConfig;

    protected ?string $projectId;

    /**
     * @var ?numeric-string
     */
    protected ?string $senderId;

    protected ?string $appId;

    protected ?string $apiKey;

    protected ?string $storageBucket;

    protected ?string $measurementId;

    protected ?string $authDomain;

    /**
     * @var ?numeric-string
     */
    protected ?string $version;

    protected ?string $packageName;

    protected ?bool $isAdsEnabled;

    protected ?bool $isAnalyticEnabled;

    protected ?bool $isAppInviteEnabled;

    protected ?bool $isGCMEnabled;

    protected ?bool $isSignInEnabled;

    /**
     * @var string[] $toCheck
     *  List of required keys to check for validity
     */
    protected array $toCheck = [
        'projectId',
        'senderId',
        'appId',
        'apiKey'
    ];

    /**
     * AbstractConfig constructor.
     *
     * @param mixed $config The configuration data.
     */
    public function __construct(mixed $config)
    {
        $this->originalConfig = $config;
        $config = $this->parseOriginalData($config);
        foreach ($this->parseData($config) as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }

    /**
     * Parses the original configuration data.
     *
     * @param mixed $config The original configuration data.
     * @return array<string, mixed> The parsed configuration data as an associative array.
     */
    protected function parseOriginalData(mixed $config) : array
    {
        return is_array($config) ? $config : [];
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array{
     *     projectId: ?string,
     *     senderId: ?numeric-string,
     *     appId: ?string,
     *     apiKey: ?string,
     *     authDomain: ?string,
     *     storageBucket: ?string,
     *     measurementId: ?string,
     *     version: ?numeric-string,
     *     packageName: ?string,
     *     isAdsEnabled: ?bool,
     *     isAnalyticEnabled: ?bool,
     *     isAppInviteEnabled: ?bool,
     *     isGCMEnabled: ?bool,
     *     isSignInEnabled: ?bool
     * }&array<string, ?string>
     */
    protected function parseData(array $config): array
    {
        $keys = [
            'projectId' => [
                'project_id',
                'PROJECT_ID'
            ],
            'senderId' => [
                'sender_id',
                'project_number',
                'messagingSenderId',
                'messaging_sender_id',
                'GCM_SENDER_ID', // IOS
            ],
            'appId' => [
                'app_id',
                'mobilesdk_app_id', // google services json
                'GOOGLE_APP_ID',  // IOS
            ],
            'apiKey' => [
                'api_key',
                'API_KEY',  // IOS
            ],
            'authDomain' => [
                'auth_domain'
            ],
            'storageBucket' => [
                'storage_bucket',
                'STORAGE_BUCKET', // IOS
            ],
            'measurementId' => [
                'measurement_id'
            ],
            'version' => [
                'version',
                'plist_version',
                'PLIST_VERSION', // IOS
            ],
            'packageName' => [
                'package_name',
                'packageName',
                'BUNDLE_ID', // IOS
            ],
            'isAdsEnabled' => [
                'is_ads_enabled',
                'IS_ADS_ENABLED'
            ],
            'isAnalyticEnabled' => [
                'is_analytic_enabled',
                'IS_ANALYTICS_ENABLED'
            ],
            'isAppInviteEnabled' => [
                'is_app_invite_enabled',
                'IS_APP_INVITE_ENABLED'
            ],
            'isGCMEnabled' => [
                'is_gcm_enabled',
                'IS_GCM_ENABLED'
            ],
            'isSignInEnabled' => [
                'is_sign_in_enabled',
                'IS_SIGN_IN_ENABLED'
            ]
        ];
        $returnValues = [];
        foreach ($keys as $property => $key) {
            $value = null;
            if (is_string($config[$property]??null) || is_scalar($config[$property]??null)) {
                $value = $config[$property];
            } else {
                foreach ($key as $k) {
                    if (is_string($config[$k]??null) || is_scalar($config[$k]??null)) {
                        $value = $config[$k];
                        break;
                    }
                }
            }
            if (!is_string($value) && !is_scalar($value)) {
                $returnValues[$property] = null;
                continue;
            }
//            elseif (is_array($key)) {
//                foreach ($key as $k) {
//                    if (is_string($config[$k]??null)) {
//                        $value = $config[$k];
//                        break;
//                    }
//                }
//            } elseif (is_string($config[$key]??null)) {
//                $value = $config[$key];
//            } else {
//                $returnValues[$key] = null;
//                continue;
//            }
            /**
             * @var non-empty-string $property
             */
            $value = match ($property) {
                'projectId' => FirebaseMessagingUtil::filterProjectId($value),
                'senderId' => FirebaseMessagingUtil::filterSenderId($value),
                'authDomain' => FirebaseMessagingUtil::filterAuthDomain($value),
                'appId' => FirebaseMessagingUtil::filterAppId($value),
                'apiKey' => FirebaseMessagingUtil::filterApiKey($value),
                'storageBucket' => FirebaseMessagingUtil::filterStorageBucket($value),
                'measurementId' => FirebaseMessagingUtil::filterMeasurementId($value),
                'version' => FirebaseMessagingUtil::filterVersion($value),
                'packageName' => FirebaseMessagingUtil::filterPackageName($value),
                'isAdsEnabled',
                'isAnalyticEnabled',
                'isAppInviteEnabled',
                'isGCMEnabled',
                'isSignInEnabled' => (static function ($e) : ?bool {
                    $e = (string) $e;
                    $e = strtolower($e);
                    return $e === '1' || $e === 'true'
                        ? true
                        : ($e === '0' || $e === 'false' ? false : null);
                })($value),
                default => null
            };
            $returnValues[$property] = $value;
        }

        /**
         * @var array{
         *      projectId: ?string,
         *      senderId: ?numeric-string,
         *      appId: ?string,
         *      apiKey: ?string,
         *      authDomain: ?string,
         *      storageBucket: ?string,
         *      measurementId: ?string,
         *      version: ?numeric-string,
         *      packageName: ?string,
         *      isAdsEnabled: ?bool,
         *      isAnalyticEnabled: ?bool,
         *      isAppInviteEnabled: ?bool,
         *      isGCMEnabled: ?bool,
         *      isSignInEnabled: ?bool
         *  }&array<string, ?string> $returnValues
         */
        return $returnValues;
    }

    public function getPackageName(): ?string
    {
        return $this->packageName??null;
    }

    public function getIsAdsEnabled(): ?bool
    {
        return $this->isAdsEnabled??null;
    }

    public function getIsAnalyticEnabled(): ?bool
    {
        return $this->isAnalyticEnabled??null;
    }

    public function getIsAppInviteEnabled(): ?bool
    {
        return $this->isAppInviteEnabled??null;
    }

    public function getIsGCMEnabled(): ?bool
    {
        return $this->isGCMEnabled??null;
    }

    public function getIsSignInEnabled(): ?bool
    {
        return $this->isSignInEnabled??null;
    }

    public function getOriginalConfig(): mixed
    {
        return $this->originalConfig??null;
    }

    public function getVersion(): ?string
    {
        return $this->version??null;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId??null;
    }

    public function getSenderId(): ?string
    {
        return $this->senderId??null;
    }

    public function getAuthDomain(): ?string
    {
        return $this->authDomain??null;
    }

    public function getAppId(): ?string
    {
        return $this->appId??null;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey??null;
    }

    public function getStorageBucket(): ?string
    {
        return $this->storageBucket??null;
    }

    public function getMeasurementId(): ?string
    {
        return $this->measurementId??null;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, mixed>|null data which can be serialized by json_encode,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize(): ?array
    {
        return $this->toArrayFormat();
    }

    /**
     * Converts the configuration data into a JSON format.
     *
     * @param int $flags Bitmask consisting of JSON constants.
     * @return ?string The configuration data as a JSON string, or null if invalid.
     */
    public function toJsonFormat(int $flags = JSON_UNESCAPED_SLASHES): ?string
    {
        $array = $this->toArrayFormat();
        if ($array) {
            return json_encode($array, $flags)?:null;
        }
        return null;
    }

    /**
     * Checks if the configuration is valid.
     *
     * @return bool True if the configuration is valid, false otherwise.
     */
    public function isValid(): bool
    {
        if (!is_string($this->projectId)
            || !is_string($this->senderId)
            || !is_string($this->appId)
            || !is_string($this->apiKey)
        ) {
            return false;
        }
        foreach ($this->toCheck as $property) {
            if (!is_string($property)
                || $property === 'projectId'
                || $property === 'appId'
                || $property === 'apiKey'
                || $property === 'senderId'
            ) {
                continue;
            }
            if (!property_exists($this, $property)) {
                return false;
            }
            if ($this->{$property} === null) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts the configuration data into an associative array format.
     *
     * @return ?array<string, mixed> The configuration data as an associative array, or null if invalid.
     */
    abstract public function toArrayFormat(): ?array;
}
