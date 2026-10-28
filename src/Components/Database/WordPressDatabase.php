<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use mysqli;
use mysqli_result;
use ReflectionObject;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Callback;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function explode;
use function is_string;
use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use const ARRAY_A;
use const MYSQLI_STORE_RESULT;
use const MYSQLI_USE_RESULT;

/**
 * @mixin WPDB
 * @property-read WPDB $WPDB
 * @property-read \wpdb $wpdb
 * @property-read mysqli|null $dbh
 * @property-read string $database_name
 * @property-read array<string, array{
 *       name: string,
 *       rows: positive-int,
 *       collation: string,
 *       columns: array<string, array{
 *           name: string,
 *           ordinal_position: positive-int,
 *           nullable: boolean,
 *           type: ?string,
 *           charset: ?string,
 *           collation: ?string,
 *           primary: boolean,
 *           index: boolean,
 *           privileges: array<int, "select"|"insert"|"update"|"references">,
 *       }>
 *   }> $schema
 * @property-read string $prefix
 * @property-read string $comments
 * @property-read string $commentmeta
 * @property-read string $links
 * @property-read string $posts
 * @property-read string $postmeta
 * @property-read string $options
 * @property-read string $terms
 * @property-read string $term_taxonomy
 * @property-read string $term_relationships
 * @property-read string $termmeta
 * @property-read string $users
 * @property-read string $usermeta
 * @property-read string $blogs
 * @property-read string $blogmeta
 * @property-read string $signups
 * @property-read string $site
 * @property-read string $sitemeta
 * @property-read string $registration_log
 */
class WordPressDatabase
{
    /**
     * @var string Active database name.
     */
    private string $database_name;

    /**
     * @var mysqli|false|null Database handle.
     */
    private null|mysqli|false $dbh;

    /**
     * @var ReflectionObject Reflection wrapper for the underlying wpdb object.
     */
    private ReflectionObject $ref;

    /**
     * @var array<string, array{
     *     name: string,
     *     tables: array<string, array{
     *          name: string,
     *          rows: positive-int,
     *          collation: string,
     *          columns: array<string, array{
     *              name: string,
     *              nullable: boolean,
     *              ordinal_position: positive-int,
     *              type: ?string,
     *              charset: ?string,
     *              collation: ?string,
     *              primary: boolean,
     *              index: boolean,
     *              privileges: array<int, "select"|"insert"|"update"|"references">,
     *          }>
     *      }>
     * }> $table_schemas
     */
    private static array $table_schemas = [];

    /**
     * @var array<string, array{
     *      name: string,
     *      rows: positive-int,
     *      collation: string,
     *      columns: array<string, array{
     *          name: string,
     *          ordinal_position: positive-int,
     *          nullable: boolean,
     *          type: ?string,
     *          charset: ?string,
     *          collation: ?string,
     *          primary: boolean,
     *          index: boolean,
     *          privileges: array<int, "select"|"insert"|"update"|"references">,
     *      }>
     *  }> $schema
     */
    private array $schema;

    /**
     * @var array<string, mixed>
     */
    private array $dataset;

    /**
     * @var array<string, true>
     */
    private array $keySet;

    /**
     * WordPressDatabase constructor.
     *
     * @param WPDB $WPDB
     */
    public function __construct(private WPDB $WPDB)
    {
    }

    /**
     * Get a reflection wrapper for the underlying wpdb object.
     *
     * @return ReflectionObject
     */
    private function getReflection(): ReflectionObject
    {
        return $this->ref ??= Reflections::object($this->WPDB->wpdb());
    }

    /**
     * Resolve the active database name from wpdb internals or SQL fallback.
     *
     * @return string Active database name.
     */
    public function getDatabaseName(): string
    {
        if (isset($this->database_name)) {
            return $this->database_name;
        }
        $dName = Callback::apply(static fn ($t) => $t->{'dbname'}, $this);
        if (is_string($dName)) {
            return $this->database_name = $dName;
        }
        return $this->database_name = $this->unBufferedQueryCallback(
            static function ($result) {
                $result = $result instanceof mysqli_result ? $result : null;
                return ($result?->fetch_assoc() ?? [])['db_name'] ?? '';
            },
            'SELECT DATABASE() as `db_name`'
        );
    }

    /**
     * Run an unbuffered query and pass the result to a callback function
     *
     * @param callable(bool|mysqli_result): mixed $callback Callback receiving the raw mysqli query result.
     * @param string $query SQL query format string.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return mixed Callback return value.
     */
    public function unBufferedQueryCallback(
        callable $callback,
        string $query,
        mixed ...$args
    ): mixed {
        try {
            $result = $this->unbufferedQuery($query, ...$args);
            return $callback($result);
        } finally {
            // make sure always closed
            $this->freeResult($result ?? null);
        }
    }

    /**
     * Determine whether the provided value is a usable mysqli result instance.
     *
     * @param mixed $result Candidate query result value.
     * @return bool True when the value is a buffered/unbuffered mysqli_result.
     * @phpstan-assert-if-true mysqli_result $result
     */
    public function isMySQLiResult(mixed $result): bool
    {
        return $result instanceof mysqli_result && isset($result->num_rows);
    }

    /**
     * Convert a mixed query result into mysqli_result when possible.
     *
     * @param mixed $result Candidate query result.
     * @return mysqli_result|null Normalized mysqli result or null.
     */
    public function filterMySQLiResult(mixed $result): ?mysqli_result
    {
        return $this->isMySQLiResult($result) ? $result : null;
    }

    /**
     * Free a result resource when it is a mysqli_result instance.
     *
     * @param mixed $result Candidate result to free.
     */
    public function freeResult(mixed $result): void
    {
        Callback::apply(static fn ($result) => $result?->free(), $this->filterMySQLiResult($result));
    }

    /**
     * Check whether a table exists in the loaded schema.
     *
     * @param string $table Table name (optionally wrapped in backticks).
     * @return bool True when the table exists.
     */
    public function hasTable(string $table): bool
    {
        $table = strtolower($table);
        if (str_starts_with($table, '`') && str_ends_with($table, '`')) {
            $table = substr($table, 1, -1);
        }
        return isset($this->getSchema()[$table]);
    }

    /**
     * Get column definitions for a table
     *
     * @param string $table Table name (optionally wrapped in backticks).
     * @param string $column Column name.
     * @return ?array{
     *     name: string,
     *     ordinal_position: positive-int,
     *     nullable: boolean,
     *     type: ?string,
     *     charset: ?string,
     *     collation: ?string,
     *     primary: boolean,
     *     index: boolean,
     *     privileges: array<int, "select"|"insert"|"update"|"references">
     * }
     */
    public function getColumnDefinitions(string $table, string $column): ?array
    {
        $table = strtolower($table);
        if (str_starts_with($table, '`') && str_ends_with($table, '`')) {
            $table = substr($table, 1, -1);
        }
        $schema = $this->getSchema()[$table] ?? null;
        if (!$schema) {
            return null;
        }
        return $schema['columns'][strtolower($column)] ?? null;
    }

    /**
     * Check if a table has a specific column.
     *
     * @param string $table Table name (optionally wrapped in backticks).
     * @param string $column Column name.
     * @return bool True if the column exists, false otherwise.
     */
    public function hasColumn(string $table, string $column): bool
    {
        return $this->getColumnDefinitions($table, $column) !== null;
    }

    /**
     * Check if a specific column in a table is of type 'datetime'.
     *
     * @param string $table Table name (optionally wrapped in backticks).
     * @param string $column Column name.
     * @return bool True if the column is of type 'datetime', false otherwise.
     */
    public function isDateTimeColumn(string $table, string $column): bool
    {
        $columns = $this->getColumnDefinitions($table, $column);
        if (!$columns) {
            return false;
        }
        return $columns['type'] === 'datetime';
    }

    /**
     * Get schema of current WPDB database
     *
     * @return array<string, array{
     *       name: string,
     *       rows: positive-int,
     *       collation: string,
     *       columns: array<string, array{
     *           name: string,
     *           ordinal_position: positive-int,
     *           nullable: boolean,
     *           type: ?string,
     *           charset: ?string,
     *           collation: ?string,
     *           primary: boolean,
     *           index: boolean,
     *           privileges: array<int, "select"|"insert"|"update"|"references">,
     *       }>
     *   }>
     */
    public function getSchema(): array
    {
        if (isset($this->schema)) {
            return $this->schema;
        }
        $dbName = $this->getDatabaseName();
        $lower = strtolower($dbName);
        if (isset(self::$table_schemas[$lower])) {
            return $this->schema = self::$table_schemas[$lower]['tables'];
        }

        $this->schema = $this->unBufferedQueryCallback(
            static function ($result) {
                $rows = [];
                $result = $result instanceof mysqli_result ? $result : null;
                while ($row = $result?->fetch_assoc()) {
                    /**
                     * @var array{
                     *      table_name: string,
                     *      table_collation: string,
                     *      table_rows: string
                     * } $row
                     */
                    $table_name = $row['table_name'];
                    $rows[strtolower($table_name)] = [
                        'name' => $table_name,
                        'collation' => $row['table_collation'],
                        'rows' => (int)$row['table_rows'],
                        'columns' => [],
                    ];
                }
                return $rows;
            },
            <<<'MYSQL'
SELECT
    TABLE_NAME AS table_name,
    TABLE_COLLATION AS table_collation,
    TABLE_ROWS AS table_rows
FROM
    `information_schema`.`TABLES`
WHERE
    TABLE_SCHEMA = %s
MYSQL,
            $dbName
        );
        self::$table_schemas[$lower] = [
            'name' => $dbName,
            'tables' => &$this->schema
        ];
        $result = $this->unbufferedQuery(
            <<<'MYSQL'
SELECT
    TABLE_NAME as table_name,
    COLUMN_NAME as column_name,
    ORDINAL_POSITION as ordinal_position,
    IS_NULLABLE as is_nullable,
    PRIVILEGES as privileges,
    COLUMN_KEY as column_key,
    COLUMN_TYPE as column_type,
    CHARACTER_SET_NAME AS column_charset,
    COLLATION_NAME AS column_collation,
    TABLE_SCHEMA AS db_name
FROM
    `information_schema`.`COLUMNS`
WHERE
    `TABLE_SCHEMA` = %s
ORDER
    by TABLE_NAME,
       ORDINAL_POSITION
MYSQL,
            $dbName
        );
        if (!$this->isMySQLiResult($result)) {
            return $this->schema;
        }
        while ($row = $result->fetch_assoc()) {
            /**
             * @var array{
             *      table_name: string,
             *      column_name: string,
             *      ordinal_position: string,
             *      is_nullable: string,
             *      privileges: string,
             *      column_key: string,
             *      column_type: string,
             *      column_charset: string,
             *      column_collation: string,
             *      db_name: string
             * } $row
             */
            $table_name = $row['table_name'];
            $lowerName = strtolower($table_name);
            if (!isset($this->schema[strtolower($table_name)])) {
                continue;
            }
            $columnName = $row['column_name'];
            $lowerColumn = strtolower($columnName);
            $this->schema[$lowerName]['columns'][$lowerColumn] = [
                'name' => $columnName,
                'type' => strtolower($row['column_type'] ?? ''),
                'nullable' => strtolower((string)$row['is_nullable']) !== 'no',
                'charset' => $row['column_charset'],
                'index' => !empty($row['column_key']),
                'primary' => $row['column_key'] === 'PRI',
                'collation' => $row['column_collation'],
                'ordinal_position' => (int)$row['ordinal_position'],
                'privileges' => explode(',', (string)$row['privileges'])
            ];
        }
        return $this->schema;
    }

    /**
     * Clone the current WordPressDatabase instance with a new WPDB connection.
     *
     * @return self
     */
    public function withCloned(): self
    {
        return new self(static::cloneWPDB($this->WPDB));
    }

    /**
     * Get the table prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->getWPDB()->prefix;
    }

    /**
     * Do prefix a table name if it is not already prefixed.
     * @param string $table
     * @return string Prefixed table name.
     */
    public function prefixIt(string $table): string
    {
        $prefix = $this->getPrefix();
        if (str_starts_with($table, $prefix) && $this->hasTable($table)) {
            return $table;
        }
        return $prefix . $table;
    }

    /**
     * Check if a query is unbuffered.
     *
     * @param mixed $result
     * @return bool
     */
    public static function isUnBufferedQuery(mixed $result): bool
    {
        return $result instanceof mysqli_result && $result->type === MYSQLI_USE_RESULT;
    }

    /**
     * Clone a WPDB instance
     *
     * @param WPDB|\wpdb|WordPressDatabase $wpdb Source database wrapper/connection.
     * @return WPDB Cloned wrapper with a new underlying wpdb connection.
     */
    public static function cloneWPDB(WPDB|\wpdb|WordPressDatabase $wpdb): WPDB
    {
        $wpdb = Callback::apply(static function ($wpdb) {
            $wpdb = new \wpdb(
                $wpdb->{'dbuser'},
                $wpdb->{'dbpassword'},
                $wpdb->{'dbname'},
                $wpdb->{'dbhost'}
            );
            $wpdb->set_prefix($wpdb->prefix);
            return $wpdb;
        }, $wpdb);
        /**
         * @var \wpdb $wpdb
         */
        // setup data
        return new WPDB($wpdb);
    }

    /**
     * Get the WPDB instance
     *
     * @return WPDB
     */
    public function getWPDB(): WPDB
    {
        return $this->WPDB;
    }

    /**
     * Get the mysqli database handle
     *
     * @return mysqli|null
     */
    public function getDbh(): ?mysqli
    {
        if (isset($this->dbh)) {
            return $this->dbh ?: null;
        }
        $dbh = Callback::apply(static fn ($db) => $db->{'dbh'}, $this->getWPDB());
        $this->dbh = $dbh ?: false;
        return $this->dbh ?: null;
    }

    /**
     * Get the dataset value
     *
     * @param string|null $name Dataset key or null to return all dataset entries.
     * @return mixed Stored dataset value, all dataset values, or null when missing.
     */
    public function getData(?string $name = null): mixed
    {
        if (!isset($name)) {
            return $this->dataset ?? [];
        }
        if (!isset($this->dataset)) {
            return null;
        }
        return $this->dataset[$name] ?? null;
    }

    /**
     * Set the dataset value
     *
     * @param string $name Dataset key.
     * @param mixed $value Value to store.
     */
    public function setData(string $name, mixed $value): void
    {
        $this->dataset ??= [];
        $this->dataset[$name] = $value;
    }

    /**
     * Magic method clone handler
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->dbh = null;
        $this->WPDB = self::cloneWPDB($this);
    }

    /**
     * Magic method property access handler
     *
     * @param string $name Property name.
     * @return mixed Computed property value or custom dataset entry.
     */
    public function __get(string $name) : mixed
    {
        switch ($name) {
            case 'database_name':
                return $this->getDatabaseName();
            case 'schema':
                return $this->getSchema();
            case 'prefix':
                return $this->getPrefix();
            case 'WPDB':
                return $this->getWPDB();
            case 'wpdb':
                return $this->getWPDB()->wpdb();
            case 'dbh':
                return $this->getDbh();
            case 'dataset':
                return $this->getData();
            case 'keySet':
                return $this->keySet ?? [];
            case 'posts':
            case 'postmeta':
            case 'comments':
            case 'commentmeta':
            case 'links':
            case 'options':
            case 'terms':
            case 'term_taxonomy':
            case 'term_relationships':
            case 'termmeta':
            case 'users':
            case 'usermeta':
            case 'blogs':
            case 'blogmeta':
            case 'signups':
            case 'site':
            case 'sitemeta':
            case 'registration_log':
                return $this->getWPDB()->$name??$name;
            default:
                if ($this->getReflection()->hasProperty($name)) {
                    return $this->getWPDB()->$name;
                }
                return $this->getData($name);
        }
    }

    /**
     * Magic method property assignment handler
     *
     * @param string $name Property name.
     * @param mixed $value Value to assign.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'database_name':
            case 'schema':
            case 'prefix':
            case 'WPDB':
            case 'wpdb':
            case 'dbh':
            case 'dataset':
            case 'keySet':
            case 'posts':
            case 'postmeta':
            case 'comments':
            case 'commentmeta':
            case 'links':
            case 'options':
            case 'terms':
            case 'term_taxonomy':
            case 'term_relationships':
            case 'termmeta':
            case 'users':
            case 'usermeta':
            case 'blogs':
            case 'blogmeta':
            case 'signups':
            case 'site':
            case 'sitemeta':
            case 'registration_log':
                return;
        }
        $this->keySet[$name] = true;
        $this->dataset[$name] = $value;
    }

    /**
     * Magic method property existence check handler
     * @param string $name Property name.
     * @return bool True if the property exists, false otherwise.
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'database_name',
            'wpdb',
            'WPDB',
            'prefix',
            'schema',
            'posts',
            'postmeta',
            'comments',
            'commentmeta',
            'links',
            'options',
            'terms',
            'term_taxonomy',
            'term_relationships',
            'termmeta',
            'users',
            'usermeta',
            'blogs',
            'blogmeta',
            'signups',
            'site',
            'sitemeta',
            'registration_log' => true,
            'dbh' => isset($this->dbh),
            default => isset($this->dataset[$name]) || $this->getReflection()->hasProperty($name)
        };
    }

    /**
     * Magic method property unset handler
     * @param string $name Property name.
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->dataset[$name], $this->keySet[$name]);
    }

    /**
     * Proxy unknown method calls to the wrapped WPDB instance.
     *
     * @param string $name Method name.
     * @param array<array-key, mixed> $arguments Method arguments.
     * @return mixed Result from the delegated WPDB call.
     */
    public function __call(string $name, array $arguments) : mixed
    {
        return $this->getWPDB()->$name(...$arguments);
    }

    /**
     * Prepare a SQL query with placeholders and arguments.
     * @param string $query SQL query format string.
     * @param mixed ...$args Placeholders and arguments for the query.
     * @return string|null Prepared query string or null when preparation fails.
     */
    public function prepareQuery(string $query, mixed ...$args): ?string
    {
        $result = $this->getWPDB()->prepare($query, ...$args);
        return is_string($result) ? $result : null;
    }

    /**
     * Get the number of rows affected by the last query
     * @param string|null $query SQL query format string or null to use the last prepared query.
     * @param int $x Zero-based row offset.
     * @param int $y Zero-based column offset.
     * @return string|null Number of affected rows or null when preparation fails.
     * @see \wpdb::get_var()
     */
    public function getVar(?string $query = null, int $x = 0, int $y = 0): ?string
    {
        return $this->getWPDB()->get_var($query, $x, $y);
    }

    /**
     * Get var with zero position
     * @param string $query SQL query format string.
     * @param mixed ...$args Placeholders and arguments for the query.
     * @return string|null Number of affected rows or null when preparation fails.
     */
    public function getVarZeroPosition(string $query, mixed ...$args): ?string
    {
        $prepare = $this->prepareQuery($query, ...$args);
        return $prepare ? $this->getVar($prepare) : null;
    }

    /**
     * Get last error message
     * @return string Last error message or empty string when no error occurred.
     */
    public function getLastError(): string
    {
        return $this->getWPDB()->last_error ?: '';
    }

    /**
     * Get charset and collation
     * @return string Charset and collation string.
     */
    public function getCharsetCollate(): string
    {
        return $this->getWPDB()->get_charset_collate();
    }

    /**
     * Quote a table or column name for use in a query.
     *
     * @template TString of string|string[]
     * @param TString $table The table or column name to quote.
     * @return TString The quoted table or column name.
     */
    public function quoteIdentifier(string|array $table): string|array
    {
        return DatabaseUtil::quoteIdentifier($table);
    }

    /**
     * Do a prepared query if the query contains placeholders
     * @param string $query SQL query format string.
     * @param mixed ...$args Placeholders and arguments for the query.
     * @return string|null Prepared query string or null when preparation fails.
     */
    public function doPrepareQuery(string $query, mixed ...$args): ?string
    {
        if (empty($args)) {
            return $query;
        }
        if (!str_contains($query, '%')
            || !preg_match(
                '~(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?~',
                $query
            )
        ) {
            return $query;
        }
        return $this->prepareQuery($query, $args);
    }

    /**
     * Get the results of a query
     *
     * @param string $query SQL query format string.
     * @param bool $returnAssoc Return rows as associative arrays when true, otherwise objects.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @phpstan-return ($returnAssoc is true ? array<int, array<array-key, mixed>>|null : array<int, object>|null)
     */
    public function getResults(string $query, bool $returnAssoc = false, mixed ...$args): array|object|null
    {
        return $this->getWPDB()->get_results($this->doPrepareQuery($query, ...$args), $returnAssoc ? ARRAY_A : OBJECT);
    }

    /**
     * Get a single row from a query
     *
     * @param string $query SQL query format string.
     * @param bool $returnAssoc Return the row as an associative array when true.
     * @param int $y Zero-based row offset.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return array<array-key, mixed>|object|null Matching row or null when none is found.
     * @phpstan-return ($returnAssoc is true ? ?array<array-key, mixed> : ?object)
     */
    public function getRow(string $query, bool $returnAssoc = false, int $y = 0, mixed ...$args): array|object|null
    {
        $prepare = $this->doPrepareQuery($query, ...$args);
        if (!$prepare) {
            return null;
        }
        return $this->getWPDB()->get_row($prepare, $returnAssoc ? ARRAY_A : OBJECT, $y);
    }

    /**
     * Run a query without placeholders
     * @param string $query SQL query format string.
     * @return bool|int Number of affected rows or false on failure.
     * @see \wpdb::query()
     */
    public function queryNoPrepare(string $query) : bool|int
    {
        return $this->getWPDB()->query($query);
    }

    /**
     * Run a query and return the number of affected rows
     * @param string $query SQL query format string.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return bool|int Number of affected rows or false on failure.
     */
    public function query(string $query, mixed ...$args): bool|int
    {
        $query = $this->doPrepareQuery($query, ...$args);
        if (!$query) {
            return false;
        }
        return $this->queryNoPrepare($query);
    }

    /**
     * Execute a query and return the result set
     *
     * @param string $query SQL query format string.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return mysqli_result|null The result set or null on failure.
     */
    public function executeQuery(string $query, mixed ...$args): ?mysqli_result
    {
        $query = $this->doPrepareQuery($query, ...$args);
        if (!$query) {
            return null;
        }
        $this->queryNoPrepare($query);
        $result = $this->{'result'} ?? null;
        return $result instanceof mysqli_result ? $result : null;
    }

    /**
     * Directly execute a query without placeholders
     *
     * @param string $query SQL query format string.
     * @param int $result_mode Result mode for the query.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return mysqli_result|bool The result set or false on failure.
     */
    public function directQuery(
        string $query,
        int $result_mode = MYSQLI_STORE_RESULT,
        mixed ...$args
    ): mysqli_result|bool {
        $dbh = $this->getDbh();
        if (!$dbh) {
            return false;
        }
        $query = $this->doPrepareQuery($query, ...$args);
        if (!$query) {
            return false;
        }
        return $dbh->query($query, $result_mode)?:false;
    }

    /**
     * Execute an unbuffered mysqli query.
     *
     * @param string $query SQL query format string.
     * @param mixed ...$args Optional placeholders for {@see prepareQuery()}.
     * @return mysqli_result|bool The unbuffered result set or false on failure.
     */
    public function unbufferedQuery(string $query, mixed ...$args): mysqli_result|bool
    {
        return $this->directQuery($query, MYSQLI_USE_RESULT, ...$args);
    }

    /**
     * Get the full comments table name.
     *
     * @return string
     */
    public function getTableComments(): string
    {
        return $this->prefixIt($this->comments);
    }

    /**
     * Get the full commentmeta table name.
     *
     * @return string
     */
    public function getTableCommentMeta(): string
    {
        return $this->prefixIt($this->commentmeta);
    }

    /**
     * Get the full links table name.
     *
     * @return string
     */
    public function getTableLinks(): string
    {
        return $this->prefixIt($this->links);
    }

    /**
     * Get the full posts table name.
     *
     * @return string
     */
    public function getTablePosts(): string
    {
        return $this->prefixIt($this->posts);
    }

    /**
     * Get the full postmeta table name.
     *
     * @return string
     */
    public function getTablePostMeta(): string
    {
        return $this->prefixIt($this->postmeta);
    }

    /**
     * Get the full options table name.
     *
     * @return string
     */
    public function getTableOptions(): string
    {
        return $this->prefixIt($this->options);
    }

    /**
     * Get the full terms table name.
     *
     * @return string
     */
    public function getTableTerms(): string
    {
        return $this->prefixIt($this->terms);
    }

    /**
     * Get the full term_taxonomy table name.
     *
     * @return string
     */
    public function getTableTermTaxonomy(): string
    {
        return $this->prefixIt($this->term_taxonomy);
    }

    /**
     * Get the full term_relationships table name.
     *
     * @return string
     */
    public function getTableTermRelationships(): string
    {
        return $this->prefixIt($this->term_relationships);
    }

    /**
     * Get the full termmeta table name.
     *
     * @return string
     */
    public function getTableTermMeta(): string
    {
        return $this->prefixIt($this->termmeta);
    }

    /**
     * Get the full users table name.
     *
     * @return string
     */
    public function getTableUsers(): string
    {
        return $this->prefixIt($this->users);
    }

    /**
     * Get the full usermeta table name.
     *
     * @return string
     */
    public function getTableUserMeta(): string
    {
        return $this->prefixIt($this->usermeta);
    }

    /**
     * Get the full blogs table name.
     *
     * @return string
     */
    public function getTableBlogs(): string
    {
        return $this->prefixIt($this->blogs);
    }

    /**
     * Get the full blogmeta table name.
     *
     * @return string
     */
    public function getTableBlogMeta(): string
    {
        return $this->prefixIt($this->blogmeta);
    }

    /**
     * Get the full signups table name.
     *
     * @return string
     */
    public function getTableSignups(): string
    {
        return $this->prefixIt($this->signups);
    }

    /**
     * Get the full site table name.
     *
     * @return string
     */
    public function getTableSite(): string
    {
        return $this->prefixIt($this->site);
    }

    /**
     * Get the full sitemeta table name.
     *
     * @return string
     */
    public function getTableSiteMeta(): string
    {
        return $this->prefixIt($this->sitemeta);
    }

    /**
     * Get the full registration_log table name.
     *
     * @return string
     */
    public function getTableRegistrationLog(): string
    {
        return $this->prefixIt($this->registration_log);
    }
}
