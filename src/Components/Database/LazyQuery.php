<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use Iterator;
use mysqli_result;
use Throwable;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ResettableInterface;
use WP_Error;
use function class_exists;
use function is_array;
use function is_numeric;
use function property_exists;
use function reset;
use function sprintf;

/**
 * @template TObject of object
 * @property string $query
 * @property string $countQuery
 * @property-read array<int, mixed> $params
 * @property-read ?WP_Error $error
 * @property-read WordPressDatabase $database
 * @property-read array<int, TObject> $resources
 * @property-read bool $executed
 * @property-read ?int $count
 * @property-read class-string<TObject> $className
 * @property-read array<array-key, mixed> $constructorParameters
 * @property-read bool $finished
 * @property-read ?TObject $current
 * @property-read ?int $currentPosition
 * @property-read ?int $key
 * @property-read array<int, TObject> $all
 *
 * @template-implements Iterator<int, TObject>
 */
class LazyQuery implements Iterator, ResettableInterface
{
    /**
     * Maximum limit for the query.
     */
    public const MAX_LIMIT = 1000;

    /**
     * Default limit for the query.
     */
    public const DEFAULT_LIMIT = 200;

    /**
     * @var array<int, TObject>
     */
    private array $resources;

    /**
     * @var int|null $currentPosition
     */
    private ?int $currentPosition = null;

    /**
     * @var ?mysqli_result
     */
    private ?mysqli_result $result;

    /**
     * @var bool $executed
     */
    private bool $executed = false;

    /**
     * @var ?WP_Error $error
     */
    private ?WP_Error $error = null;

    /**
     * @var string $query
     */
    private string $query;

    /**
     * @var string $countQuery
     */
    private string $countQuery;

    /**
     * @var array<int, mixed> $params
     */
    private array $params;

    /**
     * @var int|null
     */
    private ?int $total;

    /**
     * @var class-string<TObject> $className
     */
    private string $className;

    /**
     * @var array<array-key, mixed>
     */
    private array $constructorParameters;

    /**
     * @var bool $finished
     */
    private bool $finished = false;

    /**
     * @var WordPressDatabase $database
     */
    private WordPressDatabase $database;

    /**
     * @var string $table
     */
    private string $table;

    /**
     * @var DatabaseQuery $databaseQuery
     */
    private DatabaseQuery $databaseQuery;

    /**
     * @param DatabaseQuery $query
     * @param class-string<TObject> $className
     * @param array<array-key, mixed> $constructorParameters
     */
    public function __construct(
        DatabaseQuery $query,
        string $className = LazyFetchObject::class,
        array $constructorParameters = []
    ) {
        if (!class_exists($className)) {
            $className = LazyFetchObject::class;
        }
        $this->databaseQuery = $query;
        $limit = $query->getLimit();
        if ($limit === 0 || $limit > self::MAX_LIMIT) {
            $query = clone $query;
            $query->limit($limit === 0 ? self::DEFAULT_LIMIT : self::MAX_LIMIT);
        }
        $this->table = $query->getTable();
        $this->database = $query->getDatabase();
        $this->query = $query->getSQL();
        $this->countQuery = $query->getCountSQL();
        $this->params = $query->getSpreadParameters();
        $this->className = $className;
        $this->constructorParameters = $constructorParameters;
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     * @return static
     */
    public function withLimit(?int $limit = null, ?int $offset = null) : static
    {
        $limit ??= 0;
        $offset ??= 0;
        if ($limit === $this->databaseQuery->getLimit() && $offset === $this->databaseQuery->getOffset()) {
            return $this;
        }
        $clone = clone $this;
        $query = clone $this->databaseQuery;
        $clone->databaseQuery = $query;
        $query->limit($limit)->offset($offset);
        $clone->query = $query->getSQL();
        $clone->countQuery = $query->getCountSQL();
        return $clone;
    }

    /**
     * Get the SQL query string.
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    public function getCountQuery(): string
    {
        return $this->countQuery;
    }

    /**
     * Get the parameters for the SQL query.
     *
     * @return array<int, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get the WP_Error instance if an error occurred during query execution.
     *
     * @return ?WP_Error
     */
    public function getError(): ?WP_Error
    {
        return $this->error ?? null;
    }

    /**
     * Get the WordPressDatabase instance.
     *
     * @return WordPressDatabase
     */
    public function getDatabase(): WordPressDatabase
    {
        return $this->database;
    }

    /**
     * Get the resources fetched from the query.
     *
     * @return array<int, TObject>
     */
    public function getResources(): array
    {
        return $this->resources ?? [];
    }

    /**
     * Check if the query has been executed.
     *
     * @return bool
     */
    public function isExecuted(): bool
    {
        return $this->executed;
    }

    /**
     * Get the class name used for fetching objects.
     *
     * @return class-string<TObject>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Get the constructor parameters for the fetched objects.
     *
     * @return array<array-key, mixed>
     */
    public function getConstructorParameters(): array
    {
        return $this->constructorParameters;
    }

    /**
     * Check if the query has finished fetching all results.
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }

    /**
     * Execute the SQL query and return the mysqli_result.
     *
     * @return ?mysqli_result
     */
    private function executeQuery(): ?mysqli_result
    {
        if (isset($this->result)) {
            return $this->result;
        }
        if ($this->error || $this->executed) {
            return null;
        }
        $this->executed = true;
        try {
            $query = $this->getQuery();
            $res = $this->database->unbufferedQuery($query, ...$this->params);
            if (!$res instanceof mysqli_result) {
                $this->error = new WP_Error(
                    'ERROR_QUERY',
                    $this->database->getLastError() ?: sprintf('Error Query: %s', $query)
                );
                return null;
            }
            $this->result = $res;
        } catch (Throwable $e) {
            $this->error = new WP_Error(
                'ERROR_QUERY',
                $e->getMessage()
            );
        }
        return $this->result ?: null;
    }

    /**
     * Fetch the next object from the query result.
     *
     * @return ?TObject
     */
    public function fetch(): ?object
    {
        if ($this->isFinished()) {
            return null;
        }
        $res = $this->executeQuery();
        $fetch = $res?->fetch_object($this->className, $this->constructorParameters);
        if ($fetch) {
            $this->resources ??= [];
            $this->resources[] = $fetch;
            $this->currentPosition = count($this->resources) - 1;
        } elseif (isset($this->result)) {
            $this->getDatabase()->freeResult($this->result);
            $this->result = null;
        }
        if (!$fetch) {
            $this->finished = true;
        }
        return $fetch ?: null;
    }

    /**
     * Get the current object in the iterator.
     *
     * @return ?TObject
     */
    public function current(): ?object
    {
        if ($this->currentPosition === null) {
            return $this->fetch();
        }
        return $this->resources[$this->currentPosition] ?? null;
    }

    /**
     * Move to the next object in the iterator.
     */
    public function next(): void
    {
        $this->fetch();
    }

    /**
     * Get the current position in the iterator.
     *
     * @return ?int
     */
    public function key(): ?int
    {
        if ($this->currentPosition === null) {
            $this->fetch();
        }
        return $this->currentPosition;
    }

    /**
     * Check if the current position in the iterator is valid.
     *
     * @return bool
     */
    public function valid(): bool
    {
        if ($this->isFinished()) {
            return false;
        }
        if ($this->currentPosition === null) {
            $this->fetch();
        }
        if ($this->currentPosition === null) {
            return false;
        }
        return isset($this->resources[$this->currentPosition]);
    }

    /**
     * Rewind the iterator to the beginning.
     */
    public function rewind(): void
    {
        if ($this->currentPosition === null) {
            return;
        }
        $this->currentPosition = 0;
    }

    /**
     * Get the total number of results for the query.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total ??= $this
            ->database
            ->unBufferedQueryCallback(
                static function ($m) {
                    $count = 0;
                    if ($m instanceof mysqli_result && is_array($res = $m->fetch_assoc())) {
                        $res = reset($res);
                        $count = is_numeric($res) ? (int)$res : 0;
                    }
                    return $count;
                },
                $this->countQuery,
                ...$this->params
            );
    }

    /**
     * Fetch all objects from the query result.
     *
     * @return array<int, TObject>
     */
    public function fetchAll(): array
    {
        if ($this->finished) {
            return $this->getResources();
        }
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        while ($this->fetch()) {
        }
        $this->finished = true;
        return $this->getResources();
    }

    /**
     * Get all objects from the query result.
     *
     * @return array<int, TObject>
     */
    public function all(): array
    {
        return $this->fetchAll();
    }

    /**
     * Magic method to get properties dynamically.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) : mixed
    {
        return match ($name) {
            'total' => $this->getTotal(),
            'current' => $this->current(),
            'currentPosition', 'key' => $this->key(),
            'all' => $this->fetchAll(),
            'result' => null,
            default => $this->$name ?? null,
        };
    }

    /**
     * Magic method to check if a property is set.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return match ($name) {
            'count', 'current', 'currentPosition', 'key', 'all' => true,
            'error' => $this->error !== null,
            'result' => false,
            default => property_exists($this, $name),
        };
    }

    /**
     * Reset the query result and free resources.
     */
    public function reset(): void
    {
        if (isset($this->result)) {
            $res = $this->result;
            $this->database->freeResult($res);
        }
        $this->result = null;
        $this->resources = [];
        $this->total = null;
        $this->currentPosition = null;
    }

    /**
     * Destructor to clean up resources.
     */
    public function __destruct()
    {
        $this->reset();
    }
}
