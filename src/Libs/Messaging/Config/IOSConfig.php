<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Config;

use JsonSerializable;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match_all;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function trim;
use const PREG_SET_ORDER;

class IOSConfig extends AbstractConfig
{
    protected function parseOriginalData(mixed $config): array
    {
        if ($config instanceof JsonSerializable) {
            $config = $config->jsonSerialize();
        }
        if (is_string($config)) {
            $config = trim($config);
            if (str_starts_with($config, '{') && str_ends_with($config, '}')) {
                $array_data = json_decode($config, true);
                if (is_array($array_data)) {
                    return $array_data;
                }
            }
            if (str_contains($config, '<key>')) {
                $pattern = '/<key>(.*?)<\/key>\s*<(string|true|false|integer|real)(?:[^>]*>)(.*?)(?:<\/\2>)?/si';
                if (preg_match_all($pattern, $config, $matches, PREG_SET_ORDER)) {
                    $data = [];
                    foreach ($matches as $match) {
                        $keyName = strtoupper(trim($match[1]));
                        $type = strtolower($match[2]);
                        $val = $match[3];
                        if ($type === 'string') {
                            $data[$keyName] = $val;
                        } elseif ($type === 'true') {
                            $data[$keyName] = true;
                        } elseif ($type === 'false') {
                            $data[$keyName] = false;
                        } elseif ($type === 'integer') {
                            $data[$keyName] = (int)$val;
                        } elseif ($type === 'real') {
                            $data[$keyName] = (float)$val;
                        }
                    }
                    return $data;
                }
            }
        }
        if (is_array($config)) {
            return $config;
        }

        return [];
    }

    /**
     * Converts the configuration to an array.
     *
     * @return array<string, mixed>|null The array representation of the configuration, or null on failure.
     */
    public function toArrayFormat(): ?array
    {
        return null;
    }
}
