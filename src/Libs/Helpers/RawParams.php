<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function array_key_exists;
use function array_merge;
use function defined;
use function intval;
use function is_array;
use function is_string;
use function str_replace;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function ucwords;
use const FILTER_DEFAULT;
use const FILTER_REQUIRE_ARRAY;
use const INPUT_COOKIE;
use const INPUT_GET;
use const INPUT_POST;
use const INPUT_SERVER;

/**
 * This class is used to get the input parameters from the global variables
 * $_SERVER, $_POST, $_GET, $_REQUEST, and $_COOKIE.
 * It uses the filter_input_array function to get the parameters if the INPUT_* constants are defined.
 * If not, it falls back to using the global variables directly.
 * This helper suite is designed to get initial / raw input parameters from the global variables,
 * and it is not intended to be used for sanitizing or validating input data.
 *
 * Getting plain / raw param from initial is suitable o bypass WordPress magic quotes
 * and other filters that may be applied to the input data.
 */
final class RawParams
{
    /**
     * The maximum size of the POST data that can be accepted.
     */
    public const MAX_POST_SIZE = 1024 * 1024 * 2; // 2 MB

    /**
     * Get the server parameters from the global $_SERVER variable.
     *
     * @var array<string, mixed> The server parameters.
     */
    private static array $serverParams;

    /**
     * Get the POST parameters from the global $_POST variable.
     *
     * @var bool|array<string, mixed> The POST parameters.
     */
    private static array|bool $postParams;

    /**
     * Get the raw input content from the global php://input stream.
     *
     * @var string The raw input content.
     */
    private static string $inputContent;

    /**
     * Get the GET parameters from the global $_GET variable.
     *
     * @var array<string, mixed> The GET parameters.
     */
    private static array $getParams;

    /**
     * Get the cookie parameters from the global $_COOKIE variable.
     *
     * @var array<string, mixed> The cookie parameters.
     */
    private static array $cookieParams;

    /**
     * Get the headers from the global $_SERVER variable.
     *
     * @var array<string, string> The headers.
     */
    private static array $headers;

    /**
     * Get the raw input size from the global $_SERVER variable.
     *
     * @var int The raw input size.
     */
    private static int $rawInputSize;

    /**
     * Check if the request method is accepted (POST, PUT, PATCH).
     *
     * @var bool True if the request method is accepted, false otherwise.
     */
    private static bool $acceptedInput;

    /**
     * Filter the input parameters from the global variables using filter_input_array.
     *
     * @param int $type The type of input to filter (INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER).
     * @return mixed The filtered input parameters or null if not available.
     */
    private static function filterOf(int $type): mixed
    {
        return Callback::apply('filter_input_array', $type, FILTER_DEFAULT | FILTER_REQUIRE_ARRAY);
    }

    /**
     * Get the request method from the global $_SERVER variable.
     *
     * @return string The request method (e.g., GET, POST, PUT, DELETE).
     */
    public static function method(): string
    {
        return (string)(self::server('REQUEST_METHOD') ?? 'GET');
    }

    /**
     * Check if the request method matches the given method.
     *
     * @param string $method The method to check (e.g., GET, POST, PUT, DELETE).
     * @return bool True if the request method matches, false otherwise.
     */
    public static function methodIs(string $method): bool
    {
        $method = strtoupper($method);
        return self::method() === $method;
    }

    /**
     * Check if the request method is accepted (POST, PUT, PATCH).
     *
     * @return bool True if the request method is accepted, false otherwise.
     */
    private static function isAcceptedInput(): bool
    {
        return self::$acceptedInput ??= self::methodIs('POST')
            || self::methodIs('PUT')
            || self::methodIs('PATCH');
    }

    /**
     * Get the size of the raw input data from the global $_SERVER variable.
     *
     * @return int The size of the raw input data in bytes.
     */
    public static function inputSize(): int
    {
        if (isset(self::$rawInputSize)) {
            return self::$rawInputSize;
        }

        if (!self::isAcceptedInput()) {
            return self::$rawInputSize = 0;
        }
        $size = Callback::apply('filesize', 'php://input');
        return self::$rawInputSize = $size ?: intval(self::server('CONTENT_LENGTH', 0) ?? 0);
    }

    /**
     * Get the raw input content from the global php://input stream.
     *
     * @return ?string The raw input content.
     */
    public static function rawInput(): ?string
    {
        if (!self::isAcceptedInput()) {
            return null;
        }
        if (isset(self::$inputContent)) {
            return self::$inputContent;
        }
        $data = Callback::apply('file_get_contents', 'php://input') ?: '';
        $data = is_string($data) ? $data : '';
        if (strlen($data) > self::MAX_POST_SIZE) {
            return $data;
        }
        self::$inputContent = $data;
        return self::$inputContent;
    }

    /**
     * Get the GET parameters from the global $_GET variable.
     *
     * @return array<string, mixed> The GET parameters.
     */
    public static function getParams(): array
    {
        if (isset(self::$getParams)) {
            return self::$getParams;
        }
        if (defined('INPUT_GET')) {
            $getParams = self::filterOf(INPUT_GET);
            if (is_array($getParams)) {
                return self::$getParams = $getParams;
            }
        }
        return self::$getParams ??= Filter::removeMagicQuotes($_GET);
    }

    /**
     * Get the POST parameters from the global $_POST variable.
     * @param bool|null $wpUnfiltered
     *  A reference variable to indicate if the input size exceeds the maximum allowed size,
     *  and inout from WordPress to allow unfiltered input if needed.
     * @param-out bool $wpUnfiltered
     *  A reference variable to indicate if the input size exceeds the maximum allowed size,
     *  and inout from WordPress to allow unfiltered input if needed.
     * @return array<string, mixed> The POST parameters.
     */
    public static function postParams(?bool &$wpUnfiltered = null): array
    {
        $wpUnfiltered = false;
        if (!self::isAcceptedInput()) {
            return [];
        }
        if (isset(self::$postParams)) {
            if (is_array(self::$postParams)) {
                return self::$postParams;
            }
            if (self::inputSize() > self::MAX_POST_SIZE) {
                $wpUnfiltered = true;
                return $_POST;
            }
            return Filter::removeMagicQuotes($_POST);
        }
        if (defined('INPUT_POST')) {
            $postParams = self::filterOf(INPUT_POST);
            if (is_array($postParams)) {
                if (self::inputSize() > self::MAX_POST_SIZE) {
                    return $postParams;
                }
                return self::$postParams = $postParams;
            }
            self::$postParams = false;
        }
        if (self::inputSize() > self::MAX_POST_SIZE) {
            $wpUnfiltered = true;
            // Return the raw $_POST data without filtering if the input size exceeds the maximum allowed size.
            return $_POST;
        }
        return self::$postParams = Filter::removeMagicQuotes($_POST);
    }

    /**
     * Get the request parameters from the global $_REQUEST variable.
     *
     * @return array<string, mixed> The request parameters.
     */
    public static function requestParams(): array
    {
        /**
         * Request based on $_GET and $_POST, not $_REQUEST,
         * because $_REQUEST can be modified by PHP settings (variables_order).
         */
        return array_merge(self::getParams(), self::postParams());
    }

    /**
     * Get the cookie parameters from the global $_COOKIE variable.
     *
     * @return array<string, mixed> The cookie parameters.
     */
    public static function cookieParams(): array
    {
        if (isset(self::$cookieParams)) {
            return self::$cookieParams;
        }
        if (defined('INPUT_COOKIE')) {
            $cookieParams = self::filterOf(INPUT_COOKIE);
            if (is_array($cookieParams)) {
                return self::$cookieParams = $cookieParams;
            }
        }
        return self::$cookieParams ??= Filter::removeMagicQuotes($_COOKIE);
    }

    /**
     * Get the server parameters from the global $_SERVER variable.
     *
     * @return array<string, mixed> The server parameters.
     */
    public static function serverParams(): array
    {
        if (isset(self::$serverParams)) {
            return self::$serverParams;
        }
        if (defined('INPUT_SERVER')) {
            $serverParams = self::filterOf(INPUT_SERVER);
            if (is_array($serverParams)) {
                return self::$serverParams = $serverParams;
            }
        }
        return self::$serverParams ??= Filter::removeMagicQuotes($_SERVER);
    }

    /**
     * Normalize the header name by converting it to lowercase and replacing underscores with hyphens.
     *
     * @param string $name The header name to normalize.
     * @return string The normalized header name.
     */
    private static function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($name))));
    }

    /**
     * Get the header parameters from the global $_SERVER variable.
     *
     * @return array<string, string> The header parameters.
     */
    public static function headers(): array
    {
        if (isset(self::$headers)) {
            return self::$headers;
        }

        $headers = [];
        foreach (self::serverParams() as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = self::normalizeHeaderName(substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        return self::$headers = $headers;
    }

    /**
     * Get a parameter from the request parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cache = self::getParams();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    /**
     * Get a parameter from the POST parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        $cache = self::postParams();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    /**
     * Get a parameter from the request parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function request(string $key, mixed $default = null): mixed
    {
        $cache = self::requestParams();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    /**
     * Get a parameter from the cookie parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function cookie(string $key, mixed $default = null): mixed
    {
        $cache = self::cookieParams();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    /**
     * Get a parameter from the server parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function server(string $key, mixed $default = null): mixed
    {
        $cache = self::serverParams();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }

    /**
     * Get a parameter from the header parameters.
     * Null-Safe, if the parameter is not found, it will return the default value.
     *
     * @param string $key The key of the parameter to get.
     * @param mixed $default The default value to return if the parameter is not found.
     * @return mixed The value of the parameter or the default value if not found.
     */
    public static function header(string $key, mixed $default = null): mixed
    {
        $key = self::normalizeHeaderName($key);
        $cache = self::headers();
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }
}
