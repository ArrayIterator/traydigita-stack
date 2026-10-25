<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function headers_sent;
use function ob_end_clean;
use function ob_get_level;
use function ob_start;

class OutputBuffer
{
    /**
     * Clean all output buffer and return if headers are sent
     *
     * @return bool true headers are not sent, false headers are sent
     * if this method return true, the content can be sent to the client,
     * otherwise the content is already sent and cannot be modified
     */
    public static function clear(bool $createNewBuffer = false): bool
    {
        if (headers_sent()) {
            $hasBuffer = ob_get_level() > 0;
            if ($hasBuffer) {
                Callback::apply(static function () {
                    $elapsed = 100; // just make sure we don't get into an infinite loop, just in case
                    while ($elapsed-- > 0 && ob_get_level() > 0) {
                        ob_end_clean();
                    }
                });
            }
            if ($createNewBuffer && ob_get_level() === 0) {
                ob_start();
            }
        }
        return !headers_sent();
    }
}
