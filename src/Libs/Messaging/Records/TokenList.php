<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Records;

use Throwable;
use TrayDigita\WP\Stack\Source\Components\Database\Builder\CompositeExpression;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Components\Networks\Cloudflare;
use TrayDigita\WP\Stack\Source\Libs\Helpers\RawParams;
use TrayDigita\WP\Stack\Source\Stack;
use function array_push;
use function implode;
use function is_string;
use function restore_error_handler;
use function set_error_handler;

final class TokenList
{
    public const int MAX_LIMIT = 500;

    public const int DEFAULT_LIMIT = 100;

    public const string TABLE_NAME = 'traydigita_push_tokens';

//    private static int $total;

    public const array AVAILABLE_COLUMNS = [
        'id',
        'token',
        'topic',
        'user_agent',
        'ip_address',
        'created_at',
    ];

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
     * @var Cloudflare
     */
    public readonly Cloudflare $cloudflare;

    /**
     * @param Database $database
     * @param string $tableName
     * @param Cloudflare|null $cloudflare
     */
    public function __construct(
        Database $database,
        string $tableName = self::TABLE_NAME,
        ?Cloudflare $cloudflare = null
    ) {
        $this->database = $database;
        $this->tableName = $database->prefixIt($tableName);
        $this->cloudflare = $cloudflare ?? Stack::getInstance()->getCloudflare();
    }

    /**
     * Get the SQL statement to create the tokens table.
     *
     * @return string The SQL statement for creating the tokens table.
     */
    public function sql(): string
    {
        $database = $this->database;
        $table_tokens = $database->quoteIdentifier($this->tableName);
        $charset_collate = $database->getCharsetCollate();
        return "
CREATE TABLE IF NOT EXISTS $table_tokens (
    id BIGINT(20) unsigned NOT NULL auto_increment,
    app_id VARCHAR(255) NOT NULL,
    token TEXT NOT NULL,
    topic VARCHAR(191) DEFAULT '' NOT NULL,
    user_agent TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    KEY topic (topic),
    KEY token (token),
    KEY app_id (app_id),
    KEY ip_address (ip_address),
    UNIQUE KEY unique_token_topic (token(255), app_id, topic(50))
) $charset_collate;
            ";
    }

    /**
     * Get metadata about the tokens table, including total, success, and failed counts.
     *
     * @return array{
     *     total: int,
     *     success: int,
     *     failed: int,
     * }
     */
    public function getMetadata() : array
    {
        if (isset($this->metadata)) {
            return $this->metadata;
        }

        $table = $this->database->quoteIdentifier($this->tableName);
        $total = (int) $this->database->getVarZeroPosition("SELECT COUNT(id) FROM $table");
        $success = (int) $this->database->getVarZeroPosition("SELECT COUNT(id) FROM $table WHERE status = 1");
        $this->metadata = [
            'total' => $total,
            'success' => $success,
            'failed' => $total - $success
        ];
        return $this->metadata;
    }

    /**
     * @param string $app_id
     * @param string $token
     * @param string|null $user_agent
     * @param string|null $ip_address
     * @param string ...$topics
     * @return bool
     */
    public function insert(
        string $app_id,
        string $token,
        ?string $user_agent = null,
        ?string $ip_address = null,
        string ...$topics
    ): bool {
        if (empty($token) || empty($topics)) {
            return false;
        }
        $user_agent ??= RawParams::headerOf($this->cloudflare->serverParams, 'user-agent') ?? 'N/A';
        $ip_address ??= $this->cloudflare->clientIP;
        try {
            set_error_handler(function () {
                return false;
            });
            $table_tokens = $this->database->quoteIdentifier($this->tableName);
            $values = [];
            $placeholders = [];
            $current_time = $this->database->currentTime(true);
            foreach ($topics as $topic) {
                $placeholders[] = "(%s, %s, %s, %s, %s, %s)";
                array_push($values, $token, $app_id, $topic, $user_agent, $ip_address, $current_time);
            }
            $placeholders = implode(', ', $placeholders);
            $sql = "INSERT INTO $table_tokens (token, app_id, topic, user_agent, ip_address, created_at) 
                VALUES $placeholders
                ON DUPLICATE KEY UPDATE 
                    user_agent = VALUES(user_agent), 
                    ip_address = VALUES(ip_address), 
                    created_at = VALUES(created_at)";
            return (bool)$this->database->query($sql, $values);
        } catch (Throwable) {
            // return
        } finally {
            restore_error_handler();
        }
        return false;
    }

    /**
     * @param mixed $token
     * @return bool
     */
    public function exists(mixed $token): bool
    {
        if (!is_string($token)) {
            return false;
        }
        $token_table = $this->database->quoteIdentifier($this->tableName);
        return (bool)$this->database
            ->getVarZeroPosition("SELECT 1 FROM {$token_table} WHERE token = %s LIMIT 1", $token);
    }

    /**
     * Find tokens based on the provided expression and return a LazyTokenList.
     *
     * @param CompositeExpression|null $expression The expression to filter tokens.
     * @param int $limit The maximum number of tokens to retrieve (default: self::DEFAULT_LIMIT).
     * @param int $offset The offset for pagination (default: 0).
     * @param array<string, string|numeric> $order
     *      An associative array specifying the order of results (default: ['id' => 'DESC']).
     * @param bool $alternate Whether to use alternate query execution (default: true).
     * @param bool $unbuffered Whether to use unbuffered query execution (default: true).
     *
     * @return LazyTokenList A LazyTokenList containing the found tokens.
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
    ): LazyTokenList {
        return new LazyTokenList($this, $expression, $limit, $offset, $order, $alternate, $unbuffered);
    }
}
