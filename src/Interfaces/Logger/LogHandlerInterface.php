<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Logger;

interface LogHandlerInterface
{
    /**
     * Handle a log record.
     *
     * @param LogRecordInterface $record The log record to handle.
     * @return bool True if the record was handled, false otherwise.
     */
    public function handle(LogRecordInterface $record): bool;

    /**
     * Check if the handler is capable of handling the given log record.
     *
     * @param LogRecordInterface $record The log record to check.
     * @return bool True if the handler can handle the record, false otherwise.
     */
    public function isHandling(LogRecordInterface $record): bool;

    /**
     * Handle a batch of log records.
     *
     * @param LogRecordInterface ...$records The log records to handle.
     */
    public function handleBatch(LogRecordInterface ...$records): void;

    /**
     * Close the handler and perform any necessary cleanup.
     */
    public function close() : void;
}
