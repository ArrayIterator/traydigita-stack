<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Data;

use TrayDigita\WP\Stack\Source\Components\Data\Record\TransientData;
use TrayDigita\WP\Stack\Source\Components\Data\Traits\OptionTrait;
use TrayDigita\WP\Stack\Source\Interfaces\Data\OptionInterface;
use function doing_action;
use function is_array;
use function sprintf;
use function time;
use function trim;

final class SiteTransients implements OptionInterface
{
    use OptionTrait {
        __construct as private constructOption;
        set as private setOption;
        get as private getOption;
        all as private allOptions;
        hookUpdate as private hookUpdateOption;
    }

    /**
     * Base option key used to store site transient data.
     *
     * @var string
     */
    public const OPTION_NAME = 'site_transient';

    /**
     * Optional suffix appended to the generated option name.
     *
     * @var string
     */
    private string $suffixName = '';

    /**
     * Create a new site transient storage with an optional option-name suffix.
     *
     * @param string $suffixName Optional suffix appended to the final option name.
     */
    public function __construct(string $suffixName = '')
    {
        $this->suffixName = trim($suffixName);
        $this->constructOption();
        $this->expiration = 0;
    }

    /**
     * Filter updated option payload before it is persisted.
     *
     * Only valid, non-expired {@see TransientData} items are retained.
     *
     * @param mixed $value Value passed through the update hook.
     * @return mixed Sanitized value for storage.
     */
    public function hookUpdate(mixed $value): mixed
    {
        if (!doing_action($this->hookUpdate)) {
            return $value;
        }

        $value = $this->hookUpdateOption($value);
        if (!is_array($value)) {
            return null;
        }
        foreach ($value as $key => $val) {
            if (!$val instanceof TransientData
                || $val->expired
            ) {
                unset($value[$key]);
            }
        }

        return $value;
    }

    /**
     * Remove invalid or expired entries from a refreshed option set.
     *
     * @param array<string, mixed> $options Raw stored options.
     * @return array<string, mixed> Options containing only active transient entries.
     */
    protected function convertRefresh(array $options): array
    {
        foreach ($options as $key => $value) {
            if (!$value instanceof TransientData
                || $value->expired
            ) {
                $this->delete($key);
                unset($options[$key]);
            }
        }
        return $options;
    }

    /**
     * Retrieve all active transients as plain values.
     *
     * Expired or invalid entries are deleted before returning the result.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $transients = $this->allOptions();
        foreach ($transients as $key => $value) {
            if (!$value instanceof TransientData
                || $value->expired
            ) {
                $this->delete($key);
                unset($transients[$key]);
                continue;
            }
            $transients[$key] = $value->getValue();
        }
        return $transients;
    }

    /**
     * Get a transient value by key.
     *
     * Expired entries are removed and the default value is returned.
     *
     * @param string $name Transient key.
     * @param mixed $default Default value if the transient is missing/expired.
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $value = $this->getOption($name, $default);
        if ($value instanceof TransientData) {
            if ($value->expired) {
                $this->delete($name);
                return $default;
            }
            return $value->getValue();
        }
        return $default;
    }

    /**
     * Store a transient value.
     *
     * @param string $name Transient key.
     * @param mixed $value Value to persist.
     * @param int $expired Expiration offset in seconds.
     */
    public function set(string $name, mixed $value, int $expired = 0): void
    {
        if ($value instanceof TransientData) {
            $this->setOption($name, $value->withExpiration($expired));
        } else {
            $this->setOption($name, new TransientData($name, $value, $expired + time()));
        }
    }

    /**
     * Get the storage type identifier.
     *
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_SITE_TRANSIENT;
    }

    /**
     * Build the full option name used in persistence.
     *
     * @return string
     */
    public function getOptionName(): string
    {
        return sprintf('%s%s%s', self::PREFIX, self::OPTION_NAME, $this->suffixName);
    }
}
