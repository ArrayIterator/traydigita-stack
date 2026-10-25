<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger\Handlers;

use TrayDigita\WP\Stack\Source\Components\Logger\AbstractHandler;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;

class NoopHandler extends AbstractHandler
{
    /**
     * @inheritdoc
     * Always returns true, indicating that this handler can handle any log record.
     */
    public function isHandling(LogRecordInterface $record): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * Handles a log record by doing nothing and returning false,
     * indicating that the record should continue to be processed by other handlers.
     */
    public function handle(LogRecordInterface $record): bool
    {
        return false;
    }
}
