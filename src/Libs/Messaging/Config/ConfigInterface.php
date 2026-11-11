<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Config;

use JsonSerializable;
use const JSON_UNESCAPED_SLASHES;

interface ConfigInterface extends JsonSerializable
{
    /**
     * @return array<array-key, mixed>|mixed
     */
    public function getOriginalConfig() : mixed;

    /**
     * @return ?string
     */
    public function getProjectId() : ?string;

    /**
     * @return ?numeric-string
     */
    public function getVersion() : ?string;

    /**
     * @return ?string
     */
    public function getPackageName() : ?string;

    /**
     * @return bool|null
     */
    public function getIsAdsEnabled(): ?bool;

    /**
     * @return bool|null
     */
    public function getIsAnalyticEnabled(): ?bool;

    /**
     * @return bool|null
     */
    public function getIsAppInviteEnabled(): ?bool;

    /**
     * @return bool|null
     */
    public function getIsGCMEnabled(): ?bool;

    /**
     * @return bool|null
     */
    public function getIsSignInEnabled(): ?bool;

    /**
     * @return ?string
     */
    public function getSenderId() : ?string;

    /**
     * @return string|null
     */
    public function getAuthDomain() : ?string;

    /**
     * @return string|null
     */
    public function getAppId() : ?string;

    /**
     * @return string|null
     */
    public function getApiKey() : ?string;

    /**
     * @return string|null
     */
    public function getStorageBucket() : ?string;

    /**
     * @return string|null
     */
    public function getMeasurementId() : ?string;

    /**
     * Indicates whether the configuration is valid.
     * @return bool
     */
    public function isValid() : bool;

    /**
     * Converts the configuration to a JSON string.
     *
     * @param int $flags Optional flags for json_encode.
     * @return string|null The JSON string representation of the configuration, or null on failure.
     */
    public function toJsonFormat(int $flags = JSON_UNESCAPED_SLASHES) : ?string;

    /**
     * Converts the configuration to an array.
     *
     * @return array<string, mixed>|null The array representation of the configuration, or null on failure.
     */
    public function toArrayFormat() : ?array;
}
