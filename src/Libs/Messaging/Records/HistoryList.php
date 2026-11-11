<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Records;

use TrayDigita\WP\Stack\Source\Components\Database\Builder\CompositeExpression;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use function current_time;
use function implode;
use function is_numeric;
use function is_wp_error;
use function wp_json_encode;

final class HistoryList
{
    public const string TABLE_NAME = 'traydigita_push_history';

    public const int MAX_LIMIT = 500;

    public const int DEFAULT_LIMIT = 100;

    /**
     * @var array{
     *     total: int,
     *     success: int,
     *     failed: int,
     * }
     */
    private array $metadata;

    /**
     * @var Database
     */
    public readonly Database $database;

    /**
     * @var string
     */
    public readonly string $tableName;

    /**
     * HistoryList constructor.
     *
     * @param Database $database
     * @param string $baseTableName
     */
    public function __construct(Database $database, string $baseTableName = self::TABLE_NAME)
    {
        $this->database = $database;
        $this->tableName = $database->prefixIt($baseTableName);
    }

    public function sql(): string
    {
        $database = $this->database;
        $table_history = $database->quoteIdentifier($this->tableName);
        $charset_collate = $database->getCharsetCollate();
        return "
CREATE TABLE IF NOT EXISTS $table_history (
    id BIGINT(20) UNSIGNED NOT NULL auto_increment,
    uuid VARCHAR(60) NOT NULL,
    title TEXT NOT NULL,
    messenger_id varchar(120) NOT NULL,
    message TEXT NOT NULL,
    target_url TEXT DEFAULT NULL,
    status BOOLEAN NOT NULL,
    response_data LONGTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY `uuid` (`uuid`),
    KEY `status` (`status`),
    KEY `title` (`title`(255)),
    KEY `message` (`message`(255)),
    KEY `messenger_id` (`messenger_id`),
    KEY `target_url` (`target_url`(255))
) $charset_collate;
";
    }

    public function deleteById(int ...$ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $table = $this->tableName;
        $list = implode(', ', $ids);
        $return = $this->database->queryNoPrepare(
            "DELETE FROM $table WHERE ID IN ($list)"
        );
        return is_numeric($return) ? (int) $return : 0;
    }

    /**
     * Insert a new history record into the database.
     *
     * @param string $uuid The unique identifier for the history record.
     * @param string $messenger_id The ID of the messenger associated with the record.
     * @param string $title The title of the history record.
     * @param string|null $message The message content of the history record (optional).
     * @param string|null $url The target URL associated with the history record (optional).
     * @param bool $is_success Indicates whether the operation was successful (true) or failed (false).
     * @param mixed $result The result data to be stored in the response_data field.
     * It can be an array, object, or WP_Error.
     *
     * @return bool|int Returns the inserted record ID on success, or false on failure.
     */
    public function insert(
        string $uuid,
        string $messenger_id,
        string $title,
        ?string $message,
        ?string $url,
        bool $is_success,
        mixed $result
    ): bool|int {
        if (!Is::UUIDv7($uuid)) {
            return false;
        }
        return $this->database->insert(
            $this->tableName,
            [
                'uuid' => $uuid,
                'title' => $title,
                'messenger_id' => $messenger_id,
                'message' => $message,
                'target_url' => $url,
                'status' => $is_success,
                'response_data' => wp_json_encode(
                    is_wp_error($result) ? [
                        'code' => $result->get_error_code(),
                        'message' => $result->get_error_message()
                    ] : $result
                ),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Get the metadata of the history list, including total, success, and failed counts.
     *
     * @return array{
     *     total: int,
     *     success: int,
     *     failed: int
     * }
     */
    public function getMetadata() : array
    {
        if (isset($this->metadata)) {
            return $this->metadata;
        }

        $tableName = $this->database->quoteIdentifier($this->tableName);
        $total = (int) $this->database->getVarZeroPosition("SELECT COUNT(id) FROM $tableName");
        $success = (int) $this->database->getVarZeroPosition("SELECT COUNT(id) FROM $tableName WHERE status = 1");
        $this->metadata = [
            'total' => $total,
            'success' => $success,
            'failed' => $total - $success
        ];
        return $this->metadata;
    }

    /**
     * Find history records based on the provided expression and return a LazyHistoryList.
     *
     * @param CompositeExpression|null $expression The expression to filter history records.
     * @param int $limit The maximum number of records to retrieve (default: self::DEFAULT_LIMIT).
     * @param int $offset The offset for pagination (default: 0).
     * @param array<string, string|numeric> $order
     *      An associative array specifying the order of results (default: ['id' => 'DESC']).
     * @param bool $alternate Whether to use alternate query execution (default: true).
     * @param bool $unbuffered Whether to use unbuffered query execution (default: true).
     *
     * @return LazyHistoryList A LazyHistoryList containing the found history records.
     */
    public function findBy(
        CompositeExpression|null $expression = null,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = 0,
        array $order = [
            'id' => 'DESC'
        ],
        bool $alternate = true,
        bool $unbuffered = true
    ): LazyHistoryList {
        return new LazyHistoryList($this, $expression, $limit, $offset, $order, $alternate, $unbuffered);
    }
}
