<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use Monolog\Processor\ProcessorInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogProcessorInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;

/**
 * This class adapts a Monolog processor to the LogProcessorInterface.
 */
readonly class MonologProcessorAdapter implements LogProcessorInterface
{
    /**
     * @param ProcessorInterface $processor The Monolog processor to adapt.
     */
    public function __construct(public ProcessorInterface $processor)
    {
    }

    /**
     * Creates a new instance of MonologProcessorAdapter.
     *
     * @param ProcessorInterface $processor The Monolog processor to adapt.
     * @return self A new instance of MonologProcessorAdapter.
     */
    public static function create(ProcessorInterface $processor): self
    {
        return new self($processor);
    }

    /**
     * Processes a log record using the underlying Monolog processor.
     *
     * @param LogRecordInterface $record
     * @return LogRecordInterface
     */
    public function __invoke(LogRecordInterface $record): LogRecordInterface
    {
        $monologRecord = $record->toMonologRecord();
        $result = $this->processor->__invoke($monologRecord);
        if ($result === $monologRecord) {
            return $record;
        }
        /**
         * @noinspection PhpConditionAlreadyCheckedInspection
         */
        if ($result instanceof \Monolog\LogRecord) {
            return $record->fromMonologRecord($result);
        }
        return $record;
    }
}
