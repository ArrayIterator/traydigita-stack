<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Config;

use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_replace_callback;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function trim;

class WebConfig extends AbstractConfig
{
    /**
     * @var string[] $toCheck
     *  List of required keys to check for validity
     */
    protected array $toCheck = [
        'projectId',
        'senderId',
        'authDomain',
        'appId',
        'apiKey'
    ];

    /**
     * Parses a JavaScript object string into an associative array.
     *
     * @param string $config The JavaScript object string to parse.
     * @return ?array<string, mixed> The parsed associative array, or null if parsing fails.
     */
    protected function parseJSObject(string $config) : ?array
    {
        $config = trim($config);
        if (preg_match('~[\s,{][a-zA-Z_][a-zA-Z0-9_]*\s*:~', $config)) {
            // replace js object
            $conf = preg_replace_callback('~[\s,{]([a-zA-Z_][a-zA-Z0-9_]*)\s*:~', function ($matches) {
                return str_replace($matches[1], '"' . $matches[1] . '"', $matches[0]);
            }, $config);
            if (!is_string($conf)) {
                return null;
            }
            $array_data = json_decode($conf, true);
            if (is_array($array_data)) {
                return $array_data;
            }
        }
        return null;
    }

    /**
     * Parses the original configuration data into an associative array.
     *
     * @param mixed $config The original configuration data.
     * @return array<string, mixed> The parsed associative array.
     */
    protected function parseOriginalData(mixed $config): array
    {
        if (is_string($config)) {
            $config = trim($config);
            // tolerance of firebase config
            if (preg_match('~\s+(firebase_?)?Config\s*=\s*(\{[\s\S]*?});~i', $config, $matches)) {
                $firebaseConfigJson = $this->parseJSObject($matches[1]);
                if (is_array($firebaseConfigJson)) {
                    return $firebaseConfigJson;
                }
            }
            if (str_starts_with($config, '{') && str_ends_with($config, '}')) {
                $array_data = json_decode($config, true);
                if (is_array($array_data)) {
                    return $array_data;
                }
                $array_data = $this->parseJSObject($config);
                if (is_array($array_data)) {
                    return $array_data;
                }
            }
        } elseif (is_array($config)) {
            return $config;
        }
        return [];
    }

    /**
     * Converts the configuration data into an associative array format.
     *
     * @return ?array{
     *     projectId: ?string,
     *     messagingSenderId: ?string,
     *     authDomain: ?string,
     *     appId: ?string,
     *     apiKey: ?string,
     *     measurementId?: ?string,
     *     storageBucket?: ?string
     * } The configuration data as an associative array, or null if invalid.
     */
    public function toArrayFormat(): ?array
    {
        if (!$this->isValid()) {
            return null;
        }
        $data = [
            'apiKey' => $this->getApiKey(),
            'authDomain' => $this->getAuthDomain(),
            'projectId' => $this->getProjectId(),
            'messagingSenderId' => $this->getSenderId(),
            'appId' => $this->getAppId(),
        ];
        $measurement = $this->getMeasurementId();
        $storageBucket = $this->getStorageBucket();
        if ($measurement) {
            $data['measurementId'] = $measurement;
        }
        if ($storageBucket) {
            $data['storageBucket'] = $storageBucket;
        }
        return $data;
    }
}
