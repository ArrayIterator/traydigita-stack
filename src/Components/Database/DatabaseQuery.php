<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use mysqli_result;
use Stringable;
use TrayDigita\WP\Stack\Source\Components\Database\Builder\Expression;
use TrayDigita\WP\Stack\Source\Components\Database\Builder\QueryBuilder;
use function is_array;
use function is_numeric;
use function reset;

class DatabaseQuery
{
    /**
     * @var string $table
     */
    public readonly string $table;

    /**
     * @var QueryBuilder $queryBuilder
     */
    private QueryBuilder $queryBuilder;

    /**
     * @var mysqli_result|null $result
     */
    private ?mysqli_result $result;

    /**
     * @var int|null $count
     */
    private ?int $count = null;

    /**
     * DatabaseQuery constructor.
     *
     * @param WordPressDatabase $database
     * @param string $table
     * @param bool $unbuffered
     */
    public function __construct(
        public readonly WordPressDatabase $database,
        string $table,
        public readonly bool $unbuffered = false
    ) {
        $this->table = $this->database->prefixIt($table);
        $this->queryBuilder = (QueryBuilder::new($this->table));
    }

    /**
     * Get the limit of the query.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->queryBuilder->getLimit();
    }

    /**
     * Get the offset of the query.
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->queryBuilder->getOffset();
    }

    /**
     * Get the expression builder for the query.
     * @return Expression
     */
    public function expr(): Expression
    {
        return $this->queryBuilder->expr();
    }

    /**
     * Get the database instance for the query.
     *
     * @return WordPressDatabase
     */
    public function getDatabase(): WordPressDatabase
    {
        return $this->database;
    }

    /**
     * Get the table name for the query.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Add a WHERE condition to the query.
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function where(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): static {
        $this->count = null;
        $this->queryBuilder->where($where, ...$wheres);
        return $this;
    }

    /**
     * Add an AND WHERE condition to the query.
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function andWhere(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): static {
        $this->count = null;
        $this->queryBuilder->andWhere($where, ...$wheres);
        return $this;
    }

    /**
     * Add an OR WHERE condition to the query.
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function orWhere(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): static {
        $this->count = null;
        $this->queryBuilder->orWhere($where, ...$wheres);
        return $this;
    }

    /**
     * Add a GROUP BY clause to the query.
     *
     * @param ?string $groupBy
     * @param string ...$groupBys
     * @return $this
     */
    public function groupBy(?string $groupBy, string ...$groupBys): static
    {
        $this->count = null;
        $this->queryBuilder->groupBy($groupBy, ...$groupBys);
        return $this;
    }

    /**
     * Add additional GROUP BY clause to the query.
     *
     * @param string $groupBy
     * @param string ...$groupBys
     * @return $this
     */
    public function addGroupBy(string $groupBy, string ...$groupBys): static
    {
        $this->count = null;
        $this->queryBuilder->addGroupBy($groupBy, ...$groupBys);
        return $this;
    }

    /**
     * Add a HAVING condition to the query.
     *
     * @param string $having
     * @param string ...$havings
     * @return $this
     */
    public function having(string $having, string ...$havings): static
    {
        $this->count = null;
        $this->queryBuilder->having($having, ...$havings);
        return $this;
    }

    /**
     * Add an AND HAVING condition to the query.
     *
     * @param string $having
     * @param string ...$havings
     * @return $this
     */
    public function andHaving(string $having, string ...$havings): static
    {
        $this->count = null;
        $this->queryBuilder->andHaving($having, ...$havings);
        return $this;
    }

    /**
     * Add an OR HAVING condition to the query.
     *
     * @param string $having
     * @param string ...$havings
     * @return $this
     */
    public function orHaving(string $having, string ...$havings): static
    {
        $this->count = null;
        $this->queryBuilder->orHaving($having, ...$havings);
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string|null $orderBy
     * @param string $order
     * @return $this
     */
    public function orderBy(?string $orderBy, string $order = 'ASC'): static
    {
        $this->count = null;
        $this->queryBuilder->orderBy($orderBy, $order);
        return $this;
    }

    /**
     * Add additional ORDER BY clause to the query.
     *
     * @param string $orderBy
     * @param string $order
     * @return $this
     */
    public function addOrderBy(string $orderBy, string $order = 'ASC'): static
    {
        $this->count = null;
        $this->queryBuilder->addOrderBy($orderBy, $order);
        return $this;
    }

    /**
     * Set the offset for the query.
     *
     * @param int|null $offset
     * @return $this
     */
    public function offset(?int $offset): static
    {
        $this->count = null;
        $this->queryBuilder->offset($offset);
        return $this;
    }

    /**
     * Set the limit and offset for the query.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit(?int $limit, ?int $offset = null): static
    {
        $this->count = null;
        $this->queryBuilder->limit($limit, $offset);
        return $this;
    }

    /**
     * Set a parameter for the query.
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setParameter(
        string $name,
        mixed $value,
    ): static {
        $this->count = null;
        $this->queryBuilder->setParameter($name, $value);
        return $this;
    }

    /**
     * Set JOIN
     *
     * @param string $fromAlias
     * @param string $join
     * @param string $alias
     * @param string|Stringable|null $condition
     * @return $this
     */
    public function join(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): self {
        $this->count = null;
        $this->queryBuilder->join($fromAlias, $join, $alias, $condition);
        return $this;
    }

    /**
     * Set INNER JOIN
     *
     * @param string $fromAlias
     * @param string $join
     * @param string $alias
     * @param string|Stringable|null $condition
     * @return $this
     */
    public function innerJoin(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): static {
        $this->count = null;
        $this->queryBuilder->join($fromAlias, $join, $alias, $condition);
        return $this;
    }

    public function outerJoin(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): static {
        $this->count = null;
        $this->queryBuilder->outerJoin($fromAlias, $join, $alias, $condition);
        return $this;
    }

    /**
     * Set LEFT JOIN
     *
     * @param string $fromAlias
     * @param string $join
     * @param string $alias
     * @param string|Stringable|null $condition
     * @return $this
     */
    public function leftJoin(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): static {
        $this->count = null;
        $this->queryBuilder->leftJoin($fromAlias, $join, $alias, $condition);
        return $this;
    }

    /**
     * Set RIGHT JOIN
     *
     * @param string $fromAlias
     * @param string $join
     * @param string $alias
     * @param string|Stringable|null $condition
     * @return $this
     */
    public function rightJoin(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): static {
        $this->count = null;
        $this->queryBuilder->rightJoin($fromAlias, $join, $alias, $condition);
        return $this;
    }

    /**
     * Get the parameters for the query.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->queryBuilder->getParameters();
    }

    /**
     * Get the spread parameters for the query.
     *
     * @return array<int, mixed>
     */
    public function getSpreadParameters(): array
    {
        return $this->queryBuilder->getSpreadParameters();
    }

    public function getParameter(string $parameter): mixed
    {
        return $this->queryBuilder->getParameter($parameter);
    }

    public function removeParameter(string $parameter): static
    {
        $this->count = null;
        $this->queryBuilder->removeParameter($parameter);
        return $this;
    }

    public function getSQL(): string
    {
        return $this->queryBuilder->getSQL();
    }

    public function getCountSQL(): string
    {
        return $this->queryBuilder->getSQLForCount();
    }

    public function execute(): ?mysqli_result
    {
        if (isset($this->result)) {
            $this->getDatabase()->freeResult($this->result);
        }
        $result = $this->queryBuilder->execute(
            $this->getDatabase(),
            $this->unbuffered
        );
        return $this->result = $result instanceof mysqli_result ? $result : null;
    }

    public function getResult() : ?mysqli_result
    {
        return $this->result??null;
    }

    public function getCount() : int
    {
        return $this->count ??= $this
            ->getDatabase()
            ->unBufferedQueryCallback(
                static function ($m) {
                    $count = 0;
                    if ($m instanceof mysqli_result && is_array($res = $m->fetch_assoc())) {
                        $res = reset($res);
                        $count = is_numeric($res) ? (int) $res : 0;
                    }
                    return $count;
                },
                $this->queryBuilder->getSQLForCount(),
                ...$this->queryBuilder->getSpreadParameters()
            );
    }

    public function withClonedQueryBuilder() : static
    {
        $self = clone $this;
        $self->queryBuilder = clone $self->queryBuilder;
        return $self;
    }

    public function __destruct()
    {
        if (isset($this->result)) {
            $result = $this->result;
            $this->result = null;
            $this->getDatabase()->freeResult($result);
        }
    }
}
