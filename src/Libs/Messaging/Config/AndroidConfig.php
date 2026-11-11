<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Config;

use JsonSerializable;
use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function str_ends_with;
use function str_starts_with;
use function trim;

class AndroidConfig extends AbstractConfig
{
    protected function parseOriginalData(mixed $config): array
    {
        if ($config instanceof JsonSerializable) {
            $config = $config->jsonSerialize();
        }
        if (is_string($config)) {
            $config = trim($config);
            if (str_starts_with($config, '{') && str_ends_with($config, '}')) {
                $array_data = json_decode($config, true) ?: null;
                if (!is_array($array_data)) {
                    $config = $array_data;
                }
            }
        }
        if (!is_array($config)) {
            return [];
        }
        if (is_array($config['project_info'] ?? []) && isset($config['client'])) {
            $data = $config['project_info'];
            $client = is_array($config['client']) ? $config['client'] : [];
            $clientInfo = $client['client_info'] ?? null;
            $apiKey = $client['api_key'] ?? [];
            if (is_array($apiKey) && is_string($apiKey['current_key'])) {
                $data['api_key'] = $apiKey['current_key'];
            }
            if (is_array($clientInfo)) {
                if (is_string($clientInfo['mobilesdk_app_id'] ?? null)) {
                    $data['app_id'] = $clientInfo['mobilesdk_app_id'];
                }
                if (is_array($clientInfo['android_client_info'] ?? null)
                    && is_string($clientInfo['android_client_info']['package_name'] ?? null)
                ) {
                    $data['package_name'] = $clientInfo['android_client_info']['package_name'];
                }
            }
            $config = array_merge($config, $data);
        }
        return $config;
    }

    public function toArrayFormat(): ?array
    {
        return null;
    }
}
