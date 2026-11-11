<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Records;

use TrayDigita\WP\Stack\Source\Components\Database\Builder\CompositeExpression;
use TrayDigita\WP\Stack\Source\Components\Database\DatabaseQuery;
use TrayDigita\WP\Stack\Source\Components\Database\LazyQuery;
use function is_string;
use function min;
use function strtolower;

/**
 * @template-extends LazyQuery<History>
 */
class LazyHistoryList extends LazyQuery
{
    /**
     * LazyHistoryList constructor.
     *
     * @param HistoryList $historyList The HistoryList instance to query.
     * @param CompositeExpression|null $expression Optional expression to filter the results.
     * @param int $limit The maximum number of records to retrieve (default: HistoryList::DEFAULT_LIMIT).
     * @param int $offset The offset for pagination (default: 0).
     * @param array<string, string|numeric> $order An associative array
     *  specifying the order of results (default: ['id' => 'ASC']).
     * @param bool $alternate Whether to use alternate query execution (default: true).
     * @param bool $unbuffered Whether to use unbuffered query execution (default: true).
     */
    public function __construct(
        HistoryList $historyList,
        CompositeExpression|null $expression = null,
        int $limit = HistoryList::DEFAULT_LIMIT,
        int $offset = 0,
        array $order = [
            'id' => 'ASC'
        ],
        bool $alternate = true,
        bool $unbuffered = true
    ) {
        $database = $alternate ? $historyList->database->getAlternateDatabase() : $historyList->database->getDatabase();
        $table = $historyList->tableName;
        $query = new DatabaseQuery(
            $database,
            $table,
            $unbuffered
        );
        $limit = min($limit, HistoryList::MAX_LIMIT);
        $query->limit($limit, $offset);
        foreach ($order as $key => $desc) {
            if (!is_string($key)) {
                continue;
            }
            if (!$database->hasColumn($table, $key)) {
                continue;
            }
            $desc = is_string($desc) && strtolower($desc) === 'desc' ? 'DESC' : 'ASC';
            $query->addOrderBy($key, $desc);
        }
        $expression && $query->where($expression);
        parent::__construct($query, History::class);
    }
}
