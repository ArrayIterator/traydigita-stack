<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use Closure;
use DateTimeInterface;
use ReflectionObject;
use stdClass;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use UnitEnum;
use function addcslashes;
use function array_is_list;
use function get_resource_type;
use function gettype;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_object;
use function is_resource;
use function is_string;
use function preg_match;
use function spl_object_id;
use function str_contains;
use function str_repeat;
use function strlen;
use function var_export;

final class PrettyExport
{
    /**
     * @param mixed $data
     * @param int $width Max width of the output string
     * @param int $indent Number of spaces for indentation
     * @param int $maxDepth Maximum depth for nested structures
     * @param int $level
     * @param array<int, bool> $seen
     * @return string
     */
    public static function export(
        mixed $data,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 256,
        int $level = 0,
        array $seen = []
    ): string {
        return self::walk($data, $level, $width, $indent, $maxDepth, $seen);
    }

    /**
     * @param mixed $data Data to be exported as a PHP return statement
     * @param int $width Max width of the output string
     * @param int $indent Number of spaces for indentation
     * @param int $maxDepth Maximum depth for nested structures
     * @param string $afterDeclare String to append after the declare statement
     * @return string The PHP code string that returns the exported data
     */
    public static function exportAsPhpReturn(
        mixed $data,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 256,
        string $afterDeclare = ""
    ): string {
        return "<?php //phpcs:ignore\ndeclare(strict_types=1);\n\n{$afterDeclare}return "
            . self::export($data, $width, $indent, $maxDepth) . ";\n";
    }

    /**
     * @param mixed $data Data to be exported as a PHP return statement
     * @param int $width Max width of the output string
     * @param int $indent Number of spaces for indentation
     * @param int $maxDepth Maximum depth for nested structures
     * @return string The PHP code string that returns the exported data
     */
    public static function exportAsPhpReturnAbsPath(
        mixed $data,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 256
    ): string {
        return "<?php //phpcs:ignore\ndeclare(strict_types=1);\n\nif (!defined('ABSPATH')) {\n    return;\n}\n\nreturn "
            . self::export($data, $width, $indent, $maxDepth) . ";\n";
    }

    /**
     * @param mixed $v The value to be exported
     * @param int $level The current depth level of the recursion
     * @param int $width The maximum width of the exported string
     * @param int $indent The number of spaces to use for indentation
     * @param int $maxDepth The maximum depth of recursion
     * @param array<int, bool> $seen Tracks seen objects to prevent infinite recursion
     * @param int $offset Characters already on the current line before this value
     *                    (e.g. the "'key' => " prefix), used for the width check
     * @return string The PHP code string representation of the value
     */
    private static function walk(
        mixed $v,
        int $level,
        int $width,
        int $indent,
        int $maxDepth,
        array $seen,
        int $offset = 0
    ): string {
        // Scalar & null
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string)$v;
        }
        if (is_float($v)) {
            return self::exportFloat($v);
        }
        if (is_string($v)) {
            return self::exportString($v);
        }

        // Resource
        if (is_resource($v) || gettype($v) === 'resource (closed)') {
            return 'null /* ' . gettype($v) . (is_resource($v) ? ' ' . get_resource_type($v) : '') . ' */';
        }

        // Depth limit
        if ($level > $maxDepth) {
            return is_array($v) ? '[/* max depth */]' : 'null /* max depth: ' . $v::class . ' */';
        }

        // Array
        if (is_array($v)) {
            if ($v === []) {
                return '[]';
            }

            $isList = array_is_list($v);
            $entries = [];
            foreach ($v as $key => $value) {
                $prefix = $isList ? '' : var_export($key, true) . ' => ';
                $entries[] = $prefix . self::walk(
                    $value,
                    $level + 1,
                    $width,
                    $indent,
                    $maxDepth,
                    $seen,
                    strlen($prefix)
                );
            }
            return self::render('[', ']', $entries, $level, $width, $indent, $offset);
        }

        // Object
        if (is_object($v)) {
            // Enum -> \App\Status::Active
            if ($v instanceof UnitEnum) {
                return '\\' . $v::class . '::' . $v->name;
            }

            // Closure -> null /* Closure */
            if ($v instanceof Closure) {
                return 'null /* Closure */';
            }

            // DateTime -> new \DateTimeImmutable('2024-01-01T10:00:00.000000+07:00')
            if ($v instanceof DateTimeInterface) {
                return 'new \\' . $v::class . '(' . var_export($v->format('Y-m-d\TH:i:s.uP'), true) . ')';
            }
            if ($v instanceof ExportableInterface) {
                return $v->export($level, $width, $indent, $maxDepth, $seen);
            }

            // Circular reference guard
            $id = spl_object_id($v);
            if (isset($seen[$id])) {
                return 'null /* circular: ' . $v::class . ' */';
            }
            $seen[$id] = true;

            $entries = [];
            foreach ((new ReflectionObject($v))->getProperties() as $prop) {
                if ($prop->isStatic() || !$prop->isInitialized($v)) {
                    continue;
                }
                $prefix = var_export($prop->getName(), true) . ' => ';
                $entries[] = $prefix . self::walk(
                    $prop->getValue($v),
                    $level + 1,
                    $width,
                    $indent,
                    $maxDepth,
                    $seen,
                    strlen($prefix)
                );
            }

            if ($v instanceof stdClass) {
                $head = '(object) ';
                return $head . ($entries
                        ? self::render('[', ']', $entries, $level, $width, $indent, $offset + strlen($head))
                        : '[]');
            }

            $head = '\\' . $v::class . '::__set_state(';
            $body = $entries
                ? self::render('[', ']', $entries, $level, $width, $indent, $offset + strlen($head) + 1)
                : '[]';
            return $head . $body . ')';
        }

        return 'null /* unknown type */';
    }

    /**
     * Renders entries inline when they fit, otherwise one entry per line.
     *
     * @param string $open
     * @param string $close
     * @param array<int, string> $entries
     * @param int $level
     * @param int $width
     * @param int $indent
     * @param int $offset Characters already used on the first line before $open
     * @return string
     */
    private static function render(
        string $open,
        string $close,
        array $entries,
        int $level,
        int $width,
        int $indent,
        int $offset = 0
    ): string {
        $inline = $open . implode(', ', $entries) . $close;

        // +1 accounts for the trailing "," that follows a nested value
        $used = $level * $indent + $offset + strlen($inline) + ($level > 0 ? 1 : 0);

        if (!str_contains($inline, "\n") && $used <= $width) {
            return $inline;
        }

        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);

        return $open . "\n" . $pad . implode(",\n" . $pad, $entries) . ",\n" . $padEnd . $close;
    }

    /**
     * Strings containing control characters (newline, tab, ...) are exported as
     * double-quoted escaped strings, so no raw line breaks leak into the output
     * and break the indentation.
     */
    private static function exportString(string $s): string
    {
        if (!preg_match('/[\x00-\x1F\x7F]/', $s)) {
            return var_export($s, true);
        }

        return '"' . addcslashes($s, "\0..\37\177\\\"\$") . '"';
    }

    /**
     * @param float $f
     * @return string
     */
    private static function exportFloat(float $f): string
    {
        if (is_nan($f)) {
            return 'NAN';
        }
        if (is_infinite($f)) {
            return $f > 0 ? 'INF' : '-INF';
        }
        return var_export($f, true);
    }
}
