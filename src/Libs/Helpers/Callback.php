<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use Throwable;
use TrayDigita\WP\Stack\Source\Exceptions\CaughtException;
use function array_shift;
use function file_exists;
use function func_get_args;
use function is_file;
use function is_string;
use function ob_end_clean;
use function ob_get_level;
use function ob_start;
use function restore_error_handler;
use function set_error_handler;

final class Callback
{
    /**
     * Suppress errors and exceptions from a callback
     *
     * @template TResult of mixed
     * @template TArgs of mixed
     * @param callable(TArgs...): TResult $callback
     * @param Throwable|null $error
     * @param-out Throwable|null $error
     * @param TArgs ...$args
     * @return TResult|null
     */
    public static function suppress(
        callable $callback,
        Throwable &$error = null,
        mixed ...$args
    ): mixed {
        $error = null;
        try {
            set_error_handler(static function (
                int $errNo,
                string $errorStr,
                string $errFile,
                int $errLine
            ) use (&$error) {
                $error = new CaughtException($errorStr, 0, $errNo, $errFile, $errLine);
                return true;
            });
            return $callback(...$args);
        } catch (Throwable $e) {
            $error = $e;
            return null;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Suppress errors and exceptions from a callback
     *
     * @template TResult of mixed
     * @template TArgs of mixed
     * @param callable(TArgs): TResult $callback
     * @return TResult|null
     */
    public static function apply(
        callable $callback
    ): mixed {
        try {
            $args = func_get_args();
            array_shift($args);
            set_error_handler(static fn() => true);
            return $callback(...$args);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Suppress errors and exceptions from a callback and buffer the output
     *
     * @template TResult of mixed
     * @template TArgs of mixed
     * @param callable(TArgs...): TResult $callback
     * @param TArgs ...$args
     * @return TResult|null
     */
    public static function bufferedApply(
        callable $callback,
        mixed ...$args
    ): mixed {
        $level = ob_get_level();
        ob_start();
        $result = self::apply($callback, ...$args);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        // restore the output buffer level to the original level
        if (ob_get_level() > $level) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        return $result;
    }

    /**
     * Require a file and return its result, suppressing any output
     *
     * @param string $file
     * @return mixed
     */
    public static function require(string $file): mixed
    {
        if (!file_exists($file) || !is_file($file)) {
            return null;
        }
        return self::bufferedApply(static function (string $file = '') {
            if (!$file) {
                return null;
            }
            return require $file;
        }, $file);
    }

    /**
     * Get the content of a file, suppressing any errors
     *
     * @param string $file The file path to read
     * @param int|null $length The maximum number of bytes to read
     * @param int $offset The offset to start reading from
     * @param int|null $oversizeLimit The maximum file size allowed
     * @return string|null
     */
    public static function content(
        string $file,
        ?int $length = null,
        int $offset = 0,
        ?int $oversizeLimit = null
    ): ?string {
        if (!file_exists($file) || !is_file($file)) {
            return null;
        }
        if ($oversizeLimit && Callback::apply('filesize', $file) > $oversizeLimit) {
            return null;
        }
        $result = Callback::apply('file_get_contents', $file, false, null, $offset, $length);
        return is_string($result) ? $result : null;
    }
}
