<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use DateTimeInterface;
use mysqli;
use Serializable;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use wpdb;
use function array_map;
use function explode;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_null;
use function is_object;
use function is_scalar;
use function maybe_serialize;
use function method_exists;
use function preg_match;
use function preg_replace_callback;
use function serialize;
use function sprintf;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Database utility class for handling SQL identifiers and escaping data.
 */
class DatabaseUtil
{
    /**
     * Quote identifier for SQL query
     *
     * @template T of string|array<array-key, string>
     * @param T $table
     * @return T
     */
    public static function quoteIdentifier(string|array $table): array|string
    {
        if (is_array($table)) {
            foreach ($table as $key => $item) {
                $table[$key] = self::quoteIdentifier($item);
            }
            /**
             * @var T&array<array-key, string> $table
             */
            return $table;
        }
        $clean = trim($table);
        if (strlen($clean) > 2
            && str_starts_with($clean, '"')
            && str_ends_with($clean, '"')
        ) {
            /**
             * @var T&string $result
             */
            $result = self::quoteIdentifier($clean);
            return $result;
        }
        if ($clean === '*'
            || preg_match('~^(`.+`|[0-9]+|\*)?$~', $clean)
        ) {
            /**
             * @var T&string $result
             */
            $result = $table;
            return $result;
        }
        if (preg_match('~^(\s*[(]+\s*)([^)]+)(\s*[)]+\s*)$~', $clean, $match)) {
            /**
             * @var T&string $result
             */
            $result = sprintf(
                '%s%s%s',
                $match[1],
                self::quoteIdentifier($match[2]),
                $match[3]
            );
            return $result;
        }
        if (str_contains($table, ' ')
            && preg_match('~^(\S+)\s+(?:as\s+|)(\S+)\s*$~i', $clean, $match)
        ) {
            if (preg_match(
                '~((?:^|\s)[a-z_]+)\(([^)]+)\)~i',
                $match[1],
                $separate
            )) {
                $column_1 = sprintf(
                    '%s(%s)',
                    $separate[1],
                    self::quoteIdentifier($separate[2])
                );
            } else {
                $column_1 = self::quoteIdentifier($match[1]);
            }
            /**
             * @var T&string $result
             */
            $result = $column_1
                . ' AS '
                . self::quoteIdentifier($match[2]);
            return $result;
        }
        if (str_contains($clean, '.')) {
            if (preg_match(
                '~((?:^|\s)[a-z_]+\s*[(]+)([^)]+)([)]+)~i',
                $clean
            )) {
                /**
                 * @var T&string $result
                 */
                $result = preg_replace_callback(
                    '~((?:^|\s)[a-z_]+\s*[(]+)([^)]+)([)]+)~i',
                    function ($match) {
                        return sprintf(
                            '%s%s%s',
                            $match[1],
                            self::quoteIdentifier($match[2]),
                            $match[3]
                        );
                    },
                    $clean
                );
            } else {
                $result = implode(
                    '.',
                    array_map(
                        fn($part) => self::quoteIdentifier($part),
                        explode('.', $clean)
                    )
                );
            }
            /**
             * @var T&string $result
             */
            return $result;
        } else {
            if (preg_match('~((?:^|\s)[a-z_]+)\(([^)]+)\)~i', $clean, $match)) {
                /**
                 * @var T&string $result
                 */
                $result = sprintf(
                    '%s(%s)',
                    $match[1],
                    self::quoteIdentifier($match[2])
                );
                return $result;
            }
        }
        /**
         * @var T $clean
         */
        $clean = "`$clean`";
        return $clean;
    }

    /**
     * Escape data for SQL query
     *
     * @param mixed $data
     * @param WordPressDatabase|wpdb|mysqli|null $db
     * @return string
     */
    public static function escape(mixed $data, WordPressDatabase|wpdb|mysqli|null $db = null) : string
    {
        if (is_null($data)) {
            return 'NULL';
        }
        if (is_bool($data)) {
            return $data ? 'TRUE' : 'FALSE';
        }
        if (is_object($data)) {
            if ($data instanceof DateTimeInterface) {
                $data = $data->format('Y-m-d H:i:s');
            } elseif (method_exists($data, '__toString')) {
                $data = (string) $data;
            } elseif ($data instanceof Serializable) {
                $data = serialize($data);
            }
        }
        if (is_scalar($data)) {
            $data = (string) $data;
        } else {
            $data = maybe_serialize($data);
        }
        if (!is_scalar($data)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Data type %s of %s is not valid',
                    gettype($data),
                    is_object($data) ? get_class($data) : (string) $data
                )
            );
        }
        if (!$db) {
            global $wpdb;
            $db = $wpdb;
        }
        if (!$db) {
            throw new InvalidArgumentException(
                'Database object is not available'
            );
        }
        return $db->_escape($data);
    }
}
