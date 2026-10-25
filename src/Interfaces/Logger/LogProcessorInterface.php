<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Logger;

interface LogProcessorInterface
{
    /**
     * Process a log record.
     *
     * @param LogRecordInterface $record The log record to process.
     * @return LogRecordInterface The processed log record.
     */
    public function __invoke(LogRecordInterface $record) : LogRecordInterface;
}
