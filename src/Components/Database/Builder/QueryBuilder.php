<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database\Builder;

use mysqli_result;
use Stringable;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Database\DatabaseUtil;
use TrayDigita\WP\Stack\Source\Components\Database\WordPressDatabase;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Exceptions\QueryException;
use function array_key_exists;
use function array_keys;
use function array_unshift;
use function func_get_args;
use function func_num_args;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function key;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function trim;

class QueryBuilder implements Stringable
{
    public const TYPE_SELECT = 0;

    public const TYPE_DELETE = 1;

    public const TYPE_UPDATE = 2;

    public const TYPE_INSERT = 3;

    public const STATE_DIRTY = 0;

    public const STATE_CLEAN = 1;

    /**
     * @var array{
     *     select: array<array-key, string>,
     *     table: array<array-key, array{table: string, alias: string}>,
     *     join: array<array-key, array<string, array{
     *         joinType: string,
     *         joinTable: string,
     *         joinAlias: string,
     *         joinCondition: string|Stringable|null
     *     }>>,
     *     where: null|Stringable|string,
     *     groupBy: array<array-key, string>,
     *     having: string|Stringable|null,
     *     orderBy: array<array-key, string>,
     *     values: array<string, string|Stringable>
     * }
     */
    protected array $parts = [
        'select' => [],
        'table' => [],
        'join' => [],
        'groupBy' => [],
        'orderBy' => [],
        'values' => [],
        'where' => null,
        'having' => null,
    ];

    private ?string $sql = null;

    /**
     * @var array<array-key, mixed>
     */
    private array $parameters = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $pointers = null;

    /**
     * @var Expression
     */
    protected Expression $expr;

    /**
     * Type of query
     *
     * @var int
     */
    protected int $type = self::TYPE_SELECT;

    /**
     * State of query
     *
     * @var int
     */
    protected int $state = self::STATE_DIRTY;

    /**
     * Counter for parameters
     *
     * @var int
     */
    protected int $counter = 0;

    /**
     * Offset for query
     *
     * @var int
     */
    protected int $offset = 0;

    /**
     * Limit for query
     *
     * @var int
     */
    protected int $limit = 0;

    /**
     * QueryBuilder constructor.
     *
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->select('*')->from($table);
    }

    /**
     * Get offset
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Get limit
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get parts
     *
     * @return array<string, mixed>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Create new instance
     *
     * @param string $table
     * @return self
     */
    public static function new(string $table): self
    {
        return new self($table);
    }

    /**
     * Get expression builder
     *
     * @return Expression
     */
    public function expr(): Expression
    {
        return $this->expr ??= Expression::create();
    }

    /**
     * Get query type
     *
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Get query state
     *
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @return array<string, string>
     */
    public function getPointerDefinitions(): array
    {
        if (is_array($this->pointers)) {
            return $this->pointers;
        }
        $this->pointers = [];
        $count = 0;
        foreach ($this->getParameters() as $parameter => $i) {
            ++$count;
            $this->pointers[$parameter] = "%$count\$s";
        }
        return $this->pointers;
    }

    /**
     * Get pointer for parameter
     *
     * @param mixed $key
     * @return string|null
     */
    public function getPointer(mixed $key): ?string
    {
        if (!is_string($key)) {
            return null;
        }
        $key = $this->normalizePointer($key);
        return $this->getPointerDefinitions()[$key] ?? null;
    }

    /**
     * Normalize pointer name
     *
     * @param string $name
     * @return string
     */
    public function normalizePointer(string $name): string
    {
        $name = trim($name);
        if (!str_starts_with($name, ':')) {
            $name = ":$name";
        }
        return $name;
    }

    /**
     * Filter parameter name
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    public function filterParameter(string $name): string
    {
        $originalParameter = $name;
        $name = $this->normalizePointer($name);
        if (!preg_match('~^:[a-zA-Z_0-9\-]+$~', $name)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid parameter name: %s',
                    $originalParameter
                )
            );
        }
        return $name;
    }

    /**
     * Set parameters
     *
     * @param array<array-key, mixed> $parameters
     * @return $this
     * @throws Throwable
     */
    public function setParameters(array $parameters): static
    {
        $oldParameters = $this->getParameters();
        $pointer = $this->getPointerDefinitions();
        try {
            $this->clearParameters();
            foreach ($parameters as $name => $value) {
                $name = (string)$name;
                $this->setParameter($name, $value);
            }
        } catch (Throwable $e) {
            // restore old parameters
            $this->parameters = $oldParameters;
            $this->pointers = $pointer;
            throw $e;
        }
        return $this;
    }

    /**
     * Set parameter
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setParameter(string $name, mixed $value): self
    {
        $name = $this->filterParameter($name);
        $this->parameters[$name] = $value;
        $this->pointers = null;
        return $this;
    }

    /**
     * Get parameter
     *
     * @param string $name
     * @return mixed|null
     */
    public function getParameter(string $name): mixed
    {
        return $this->parameters[$this->normalizePointer($name)] ?? null;
    }

    /**
     * Remove parameter
     *
     * @param string $name
     * @return $this
     */
    public function removeParameter(string $name): self
    {
        $this->pointers = null;
        unset($this->parameters[$this->normalizePointer($name)]);
        return $this;
    }

    /**
     * Clear parameters
     *
     * @return $this
     */
    public function clearParameters(): self
    {
        $this->parameters = [];
        $this->pointers = null;
        return $this;
    }

    /**
     * Get parameters
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get parameters for spread
     *
     * @return array<int, mixed>
     */
    public function getSpreadParameters(): array
    {
        $values = [];
        foreach ($this->getParameters() as $parameter) {
            if ($parameter !== null && !is_scalar($parameter)) {
                $values[] = DatabaseUtil::escape($parameter);
                continue;
            }
            $values[] = $parameter;
        }
        return $values;
    }

    /**
     * Set offset
     *
     * @param int|null $offset
     * @return $this
     */
    public function offset(?int $offset): self
    {
        $this->state = self::STATE_DIRTY;
        $this->offset = max(0, $offset ?? 0);
        return $this;
    }

    /**
     * Set limit
     *
     * @param int|null $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit(?int $limit, ?int $offset = null): self
    {
        $this->state = self::STATE_DIRTY;
        $this->limit = max(0, $limit ?? 0);
        if (func_num_args() > 1) {
            $this->offset($offset);
        }
        return $this;
    }

    /**
     * Add part to query
     *
     * @param string $partName
     * @param string|array<array-key, mixed>|Stringable $sqlPart
     * @param bool $append
     * @return $this
     */
    public function add(string $partName, string|array|Stringable $sqlPart, bool $append = false): self
    {
        // if part is not exists then return
        if (array_key_exists($partName, $this->parts) === false) {
            return $this;
        }

        $isArray = is_array($sqlPart);
        $isMultiple = is_array($this->parts[$partName]);
        if ($isMultiple && !$isArray) {
            $sqlPart = [$sqlPart];
        }

        $this->state = self::STATE_DIRTY;
        if (!$append) {
            $this->parts[$partName] = $sqlPart; // @phpstan-ignore-line
            return $this;
        }

        if (is_array($sqlPart)
            && in_array($partName, ['orderBy', 'groupBy', 'values'])
        ) {
            /**
             * @var array<string, string> $sqlPart
             */
            foreach ($sqlPart as $key => $part) {
                $this->parts[$partName][$key] = $part; // @phpstan-ignore-line
            }
            return $this;
        }

        $key = $isArray ? key($sqlPart) : false;
        if ($isArray && is_array($sqlPart[$key])) {
            $this->parts[$partName][$key][] = $sqlPart[$key]; // @phpstan-ignore-line
        } elseif ($isMultiple) {
            $this->parts[$partName][] = $sqlPart; // @phpstan-ignore-line
        } else {
            $this->parts[$partName] = $sqlPart; // @phpstan-ignore-line
        }

        return $this;
    }

    /**
     * Set SELECT
     *
     * @param string|null $select
     * @param string ...$selects
     * @return $this
     */
    public function select(string $select = null, string ...$selects): self
    {
        $this->type = self::TYPE_SELECT;
        if ($select !== null) {
            array_unshift($selects, $select);
        }
        if (empty($select)) {
            return $this;
        }
        $this->resetQueryPart('select');
        $this->add('select', $selects);
        return $this;
    }

    /**
     * Add/append SELECT
     *
     * @param string $select
     * @param string ...$selects
     * @return $this
     */
    public function addSelect(string $select, string ...$selects): self
    {
        array_unshift($selects, $select);
        $this->add('select', $selects, true);
        return $this;
    }

    /**
     * Delete
     *
     * @param string|null $table
     * @param string|null $alias
     * @return $this
     */
    public function delete(string $table = null, string $alias = null): self
    {
        $this->type = self::TYPE_DELETE;
        if (!$table) {
            return $this;
        }

        $this->add('table', [
            [
                'table' => $table,
                'alias' => $alias ?? ''
            ]
        ]);
        return $this;
    }

    /**
     * Update
     *
     * @param string|null $table
     * @param string|null $alias
     * @return $this
     */
    public function update(string $table = null, string $alias = null): self
    {
        $this->type = self::TYPE_UPDATE;
        if (!$table) {
            return $this;
        }
        $this->add('table', [
            [
                'table' => $table,
                'alias' => $alias ?? ''
            ]
        ]);
        return $this;
    }

    /**
     * Insert
     *
     * @param string|null $table
     * @param string|null $alias
     * @return $this
     */
    public function insert(string $table = null, string $alias = null): self
    {
        $this->type = self::TYPE_INSERT;
        if (!$table) {
            return $this;
        }
        $this->add('table', [
            [
                'table' => $table,
                'alias' => $alias ?? ''
            ]
        ]);
        return $this;
    }

    /**
     * Set table
     *
     * @param string $table
     * @param string|null $alias
     * @param bool $append
     * @return $this
     */
    public function table(string $table, string $alias = null, bool $append = false): self
    {
        // get table and aliases
        if (!$alias
            && str_contains(trim($table), ' ')
            && preg_match(
                '~^(\S+)\s+(?:AS\s+)?(\S+)$~i',
                trim($table),
                $matches
            )
        ) {
            $table = $matches[1];
            $alias = $matches[2];
        }
        $table = [
            'table' => $table,
            'alias' => $alias ?? ''
        ];
        if (!$append) {
            $table = [$table];
        }

        $this->add('table', $table, $append);
        return $this;
    }

    /**
     * Set FROM
     *
     * @param string $table
     * @param string|null $alias
     * @return $this
     * @see table()
     */
    public function from(string $table, string $alias = null): self
    {
        return $this->table($table, $alias);
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
        return $this->innerJoin($fromAlias, $join, $alias, $condition);
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
    ): self {
        $this->add(
            'join',
            [
                $fromAlias => [
                    'joinType' => 'INNER',
                    'joinTable' => $join,
                    'joinAlias' => $alias,
                    'joinCondition' => $condition,
                ]
            ],
            true
        );
        return $this;
    }

    /**
     * Set OUTER JOIN
     *
     * @param string $fromAlias
     * @param string $join
     * @param string $alias
     * @param string|Stringable|null $condition
     * @return $this
     */
    public function outerJoin(
        string $fromAlias,
        string $join,
        string $alias,
        string|Stringable|null $condition = null
    ): self {
        $this->add(
            'join',
            [
                $fromAlias => [
                    'joinType' => 'OUTER',
                    'joinTable' => $join,
                    'joinAlias' => $alias,
                    'joinCondition' => $condition,
                ]
            ],
            true
        );
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
    ): self {
        $this->add(
            'join',
            [
                $fromAlias => [
                    'joinType' => 'LEFT',
                    'joinTable' => $join,
                    'joinAlias' => $alias,
                    'joinCondition' => $condition,
                ]
            ],
            true
        );
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
    ): self {
        $this->add(
            'join',
            [
                $fromAlias => [
                    'joinType' => 'RIGHT',
                    'joinTable' => $join,
                    'joinAlias' => $alias,
                    'joinCondition' => $condition,
                ]
            ],
            true
        );
        return $this;
    }

    /**
     * Set value
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setValue(string $key, mixed $value): self
    {
        $this->add('values', [$key => $value], true);
        return $this;
    }

    /**
     * Set value
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set(string $key, mixed $value): self
    {
        return $this->setValue($key, $value);
    }

    /**
     * Set values
     *
     * @param array<string, mixed> $values
     * @return $this
     */
    public function setValues(array $values): self
    {
        $this->resetQueryPart('values');
        $this->add('values', $values);
        return $this;
    }

    /**
     * Set where
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function where(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): self {
        if (!$where instanceof CompositeExpression) {
            $where = new CompositeExpression(
                CompositeExpression::TYPE_AND,
                $where
            );
        }
        $this->resetQueryPart('where');
        $where->addMultiple(...$wheres);
        $this->add('where', $where);
        return $this;
    }

    /**
     * Escape search string
     *
     * @param string $search
     * @return string
     */
    public function escapeSearch(string $search): string
    {
        return '%' . str_replace('%', '\\%', $search) . '%';
    }

    /**
     * Set and where
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function andWhere(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): self {
        $args = func_get_args();
        $wherePart = $this->getQueryPart('where');
        if ($wherePart === null) {
            return $this->where(...$args);
        }
        if ($wherePart instanceof CompositeExpression
            && $wherePart->getType() === CompositeExpression::TYPE_AND
        ) {
            $wherePart->addMultiple(...$args);
        } else {
            if ($wherePart) {
                array_unshift($args, $wherePart);
            }
            $wherePart = new CompositeExpression(
                CompositeExpression::TYPE_AND,
                ...$args
            );
        }
        $this->add('where', $wherePart, true);
        return $this;
    }

    /**
     * Set or where
     *
     * @param string|Stringable $where
     * @param string|Stringable ...$wheres
     * @return $this
     */
    public function orWhere(
        string|Stringable $where,
        string|Stringable ...$wheres
    ): self {
        $wherePart = $this->getQueryPart('where');
        $args = func_get_args();
        if ($wherePart instanceof CompositeExpression
            && $wherePart->getType() === CompositeExpression::TYPE_OR
        ) {
            $wherePart->addMultiple(...$args);
        } else {
            if ($wherePart) {
                array_unshift($args, $wherePart);
            }
            $wherePart = new CompositeExpression(
                CompositeExpression::TYPE_OR,
                ...$args
            );
        }
        $this->add('where', $wherePart, true);
        return $this;
    }

    /**
     * Set group by
     *
     * @param ?string $groupBy null to reset group by
     * @param string ...$groupBys
     * @return $this
     */
    public function groupBy(?string $groupBy, string ...$groupBys): self
    {
        if ($groupBy === null) {
            return $this->resetQueryPart('groupBy');
        }
        array_unshift($groupBys, $groupBy);
        $this->resetQueryPart('groupBy');
        $this->add('groupBy', $groupBys);
        return $this;
    }

    /**
     * Add/append group by
     *
     * @param string $groupBy
     * @param string ...$groupBys
     * @return $this
     */
    public function addGroupBy(string $groupBy, string ...$groupBys): self
    {
        array_unshift($groupBys, $groupBy);
        $this->add('groupBy', $groupBys, true);
        return $this;
    }

    /**
     * Set having
     *
     * @param null|string|Stringable $having null to reset having
     * @param string|Stringable ...$havings
     * @return $this
     */
    public function having(null|string|Stringable $having, string|Stringable ...$havings): self
    {
        if ($having === null) {
            return $this->resetQueryPart('having');
        }
        if (!$having instanceof CompositeExpression) {
            $having = new CompositeExpression(
                CompositeExpression::TYPE_AND,
                $having
            );
        }
        $this->resetQueryPart('having');
        $having->addMultiple(...$havings);
        $this->add('having', $having);
        return $this;
    }

    /**
     * Set and having
     *
     * @param string|Stringable $having
     * @param string|Stringable ...$havings
     * @return $this
     */
    public function andHaving(string|Stringable $having, string|Stringable ...$havings): self
    {
        $args = func_get_args();
        $havingPart = $this->getQueryPart('having');
        if ($havingPart instanceof CompositeExpression
            && $havingPart->getType() === CompositeExpression::TYPE_AND
        ) {
            $havingPart->addMultiple(...$args);
        } else {
            if ($havingPart) {
                array_unshift($args, $havingPart);
            }
            $havingPart = new CompositeExpression(
                CompositeExpression::TYPE_AND,
                ...$args
            );
        }
        $this->add('having', $havingPart, true);
        return $this;
    }

    /**
     * Set or having
     *
     * @param string|Stringable $having
     * @param string|Stringable ...$havings
     * @return $this
     */
    public function orHaving(string|Stringable $having, string|Stringable ...$havings): self
    {
        $args = func_get_args();
        $havingPart = $this->getQueryPart('having');
        if ($havingPart instanceof CompositeExpression
            && $havingPart->getType() === CompositeExpression::TYPE_OR
        ) {
            $havingPart->addMultiple(...$args);
        } else {
            if ($havingPart) {
                array_unshift($args, $havingPart);
            }
            $havingPart = new CompositeExpression(
                CompositeExpression::TYPE_OR,
                ...$args
            );
        }
        $this->add('having', $havingPart, true);
        return $this;
    }

    /**
     * Set Order by
     *
     * @param string|null $orderBy
     * @param string $order
     * @return $this
     */
    public function orderBy(?string $orderBy, string $order = 'ASC'): self
    {
        if ($orderBy === null) {
            return $this->removeOrderBy();
        }
        $this->resetQueryPart('orderBy');
        $this->add('orderBy', [$orderBy => $order]);
        return $this;
    }

    /**
     * Remove order by
     *
     * @param string|null $orderBy
     * @return $this
     */
    public function removeOrderBy(?string $orderBy = null): static
    {
        if ($orderBy !== null) {
            unset($this->parts['orderBy'][$orderBy]);
        } else {
            $this->resetQueryPart('orderBy');
        }
        return $this;
    }

    /**
     * Add/append order by
     *
     * @param string $orderBy
     * @param string $order
     * @return $this
     */
    public function addOrderBy(string $orderBy, string $order = 'ASC'): self
    {
        $this->add('orderBy', [$orderBy => $order], true);
        return $this;
    }

    /**
     * Check if query part exists
     *
     * @param string $queryPart
     * @return bool
     */
    public function hasQueryPart(string $queryPart): bool
    {
        return isset($this->parts[$queryPart]);
    }

    /**
     * Get query part
     *
     * @param string $queryPart
     * @return array<array-key, mixed>|Stringable|string|null
     */
    public function getQueryPart(string $queryPart): array|Stringable|string|null
    {
        return $this->parts[$queryPart] ?? null;
    }

    /**
     * Get query parts
     *
     * @return array{
     *      select: array<array-key, string>,
     *      table: array<array-key, array{table: string, alias: string}>,
     *      join: array<array-key, array<string, array{
     *          joinType: string,
     *          joinTable: string,
     *          joinAlias: string,
     *          joinCondition: string|Stringable|null
     *      }>>,
     *      groupBy: array<array-key, string>,
     *      where: null|Stringable|string,
     *      having: string|Stringable|null,
     *      orderBy: array<array-key, string>,
     *      values: array<string, string|Stringable>
     *  }
     */
    public function getQueryParts(): array
    {
        return $this->parts;
    }

    /**
     * Reset query part
     *
     * @param string $queryPart
     * @return $this
     */
    public function resetQueryPart(string $queryPart): static
    {
        if (!isset($this->parts[$queryPart])) {
            return $this;
        }
        $this->state = self::STATE_DIRTY;
        if ($queryPart === 'where' || $queryPart === 'having') {
            $this->parts[$queryPart] = null;
            return $this;
        }
        $this->parts[$queryPart] = [];
        return $this;
    }

    /**
     * Normalize the where clause
     *
     * @param string|Stringable $where
     * @return string
     */
    private function normalizeWhere(string|Stringable $where): string
    {
        $where = (string)preg_replace_callback(
            '~
                    (?!`)(^|\s+|\()(\b[a-z._]+)
                    (\s*[<>=]+\s*|\s+IN\s*\(|\s+IS\s+(?:NOT\s+)?)
                    \s*(\'|:[a-z_]|\?(?:[\s),]?|$)|NULL|(\b[a-z._]+$))
                ~ix',
            function ($m) {
                if (isset($m[5]) && $m[4] === $m[5]) {
                    $m[4] = DatabaseUtil::quoteIdentifier($m[4]);
                }
                return $m[1] . DatabaseUtil::quoteIdentifier($m[2]) . $m[3] . $m[4];
            },
            (string)$where
        );
        // make 2 vars prevent checking
        $pattern = '~\'[^\'\\\]*(?:\\\.[^\'\\\]*)*\'';
        $pattern .= '(*SKIP)(*F)|(:[a-zA-Z0-9_]+)~';
        // return preg_replace_callback('~(^|[\s=,])(:[^\s:]+)([\s,]|$)~', function ($e) use ($keys) {
        return (string)preg_replace_callback($pattern, function ($match) {
            $key = $this->getPointer($match[1]);
            if ($key) {
                return $key;
            }
            throw new QueryException(
                sprintf('Parameter :%s is not exists', $key)
            );
        }, $where);
    }

    /**
     * Get SQL Query for select
     *
     * @return string SQL query for select
     */
    public function getSQLForSelect(): string
    {
        $fromClauses = [];
        $selects = $this->parts['select'];
        if (empty($selects)) {
            $selects = ['*'];
        }

        $sql = 'SELECT '
            . implode(
                ', ',
                DatabaseUtil::quoteIdentifier($selects)
            )
            . ' FROM';
        $tables = $this->parts['table'];
        foreach ($tables as $from) {
            $fromClause = DatabaseUtil::quoteIdentifier($from['table']);
            if (!empty($from['alias'])) {
                $fromClause .= ' AS ' . DatabaseUtil::quoteIdentifier($from['alias']);
            }

            if (isset($this->parts['join'][$from['alias']])) {
                foreach ($this->parts['join'][$from['alias']] as $join) {
                    $fromClause .= ' ' . $join['joinType']
                        . ' JOIN '
                        . DatabaseUtil::quoteIdentifier($join['joinTable']);
                    if (!empty($join['joinAlias'])) {
                        $fromClause .= ' AS ' . DatabaseUtil::quoteIdentifier((string)$join['joinAlias']);
                    }
                    if (!empty($join['joinCondition'])) {
                        if ($join['joinCondition'] instanceof CompositeExpression) {
                            $exp = new CompositeExpression(
                                $join['joinCondition']->getType()
                            );
                            foreach ($join['joinCondition']->getParts() as $part) {
                                $part = $this->normalizeWhere((string)$part);
                                if ($part) {
                                    $exp->add($this->normalizeWhere($part));
                                }
                            }
                            $join['joinCondition'] = $exp;
                        }
                        $fromClause .= ' ON ' . $join['joinCondition'];
                    }
                }
            }
            $fromClauses[$from['alias']] = $fromClause;
        }
        foreach ($this->parts['join'] as $fromAlias => $joins) {
            if (!isset($fromClauses[$fromAlias])) {
                throw new QueryException(
                    sprintf(
                        'Cannot find FROM clause for alias %s',
                        $fromAlias
                    )
                );
            }
        }

        $sql .= ' ' . implode(', ', $fromClauses);
        if (!empty($this->parts['where'])) {
            // normalize
            $where = $this->normalizeWhere($this->parts['where']);
            if (!empty($where)) {
                $sql .= ' WHERE ' . $where;
            }
        }
        if (!empty($this->parts['groupBy'])) {
            $sql .= ' GROUP BY ';
            $groups = [];
            foreach ($this->parts['groupBy'] as $groupBy) {
                $groups[] = DatabaseUtil::quoteIdentifier($groupBy);
            }
            $sql .= implode(', ', $groups);
        }
        if (!empty($this->parts['having'])) {
            $sql .= ' HAVING ' . $this->parts['having'] . ' ';
        }
        if (!empty($this->parts['orderBy'])) {
            $sql .= ' ORDER BY ';
            $orders = [];
            foreach ($this->parts['orderBy'] as $orderBy => $order) {
                $orders[] = DatabaseUtil::quoteIdentifier($orderBy) . ' ' . $order;
            }
            $sql .= implode(', ', $orders);
        }

        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    /**
     * Get SQL query for update
     *
     * @return string SQL query for update
     */
    public function getSQLForUpdate(): string
    {
        $table = $this->parts['table'][0] ?? [];
        if (empty($table)) {
            throw new QueryException('No table has been defined for update');
        }
        $tableName = DatabaseUtil::quoteIdentifier($table['table']);
        if (!empty($table['alias'])) {
            $tableName .= ' AS ' . DatabaseUtil::quoteIdentifier($table['alias']);
        }
        $sql = 'UPDATE ' . $tableName;
        if ($this->parts['values']) {
            $parts = [];
            foreach ($this->parts['values'] as $key => $value) {
                $parts[] = DatabaseUtil::quoteIdentifier($key) . ' = ' . $value;
            }
            $sql .= ' SET ';
            $sql .= implode(', ', $parts);
        }
        if ($this->parts['where']) {
            $where = $this->normalizeWhere($this->parts['where']);
            if (!empty($where)) {
                $sql .= ' WHERE ' . $where;
            }
        }
        return $sql;
    }

    /**
     * @return string SQL query for delete
     */
    public function getSQLForDelete(): string
    {
        $table = $this->parts['table'][0] ?? [];
        if (empty($table)) {
            throw new QueryException('No table has been defined for delete');
        }
        $tableName = DatabaseUtil::quoteIdentifier($table['table']);
        if (!empty($table['alias'])) {
            $tableName .= ' AS ' . DatabaseUtil::quoteIdentifier($table['alias']);
        }
        /** @noinspection SqlWithoutWhere */
        $sql = 'DELETE FROM ' . $tableName;
        if (!empty($this->parts['where'])) {
            $where = $this->normalizeWhere($this->parts['where']);
            if (!empty($where)) {
                $sql .= ' WHERE ' . $where;
            }
        }
        return $sql;
    }

    /**
     * Get SQL query for insert
     *
     * @return string SQL query for insert
     */
    public function getSQLForInsert(): string
    {
        $table = $this->parts['table'][0] ?? '';
        if (!empty($table)) {
            $table = DatabaseUtil::quoteIdentifier($table['table']);
        }
        $sql = 'INSERT INTO ' . $table;
        if ($this->parts['values']) {
            $values = $this->parts['values'];
            foreach ($values as $k => $v) {
                $pointer = $this->getPointer($k);
                if ($pointer) {
                    $values[$k] = $pointer;
                }
            }
            $sql .= ' (' . implode(', ', DatabaseUtil::quoteIdentifier(array_keys($this->parts['values']))) . ')';
            $sql .= ' VALUES (' . implode(', ', $values) . ')';
        }
        return $sql;
    }

    /**
     * Get SQL query for count
     *
     * @param string|null $identity
     * @param string|null $alias
     * @return string
     */
    public function getSQLForCount(?string $identity = null, ?string $alias = null): string
    {
        $clone = clone $this;
        $identity = $identity ? DatabaseUtil::quoteIdentifier($identity) : '*';
        $alias = $alias ? DatabaseUtil::quoteIdentifier($alias) : 'count';
        $clone->limit(null, null);
        return $clone->select("COUNT($identity) as $alias")->getSQLForSelect();
    }

    /**
     * Get SQL query as string
     *
     * @return string
     * @throws QueryException
     */
    public function getSQL(): string
    {
        if ($this->sql !== null && $this->state === self::STATE_CLEAN) {
            return $this->sql;
        }
        $sql = match ($this->getType()) {
            self::TYPE_SELECT => $this->getSQLForSelect(),
            self::TYPE_UPDATE => $this->getSQLForUpdate(),
            self::TYPE_DELETE => $this->getSQLForDelete(),
            self::TYPE_INSERT => $this->getSQLForInsert(),
            default => throw new QueryException('No query type has been defined'),
        };
        $this->sql = $sql;
        $this->state = self::STATE_CLEAN;
        return $sql;
    }

    public function execute(
        WordPressDatabase $database,
        bool $unbuffered = false
    ): mysqli_result|bool|null {
        if ($unbuffered) {
            return $database->unbufferedQuery($this->getSQL(), ...$this->getSpreadParameters());
        }
        return $database->executeQuery($this->getSQL(), ...$this->getSpreadParameters());
    }

    /**
     * Get SQL query as string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getSQL();
    }
}
