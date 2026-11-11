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
 * @template-extends LazyQuery<Token>
 */
class LazyTokenList extends LazyQuery
{
    /**
     * LazyTokenList constructor.
     *
     * @param TokenList $tokenList
     * @param CompositeExpression|null $expression
     * @param int $limit
     * @param int $offset
     * @param array<string, string|numeric> $order
     * @param bool $alternate
     * @param bool $unbuffered
     */
    public function __construct(
        TokenList $tokenList,
        CompositeExpression|null $expression = null,
        int $limit = TokenList::DEFAULT_LIMIT,
        int $offset = 0,
        array $order = [
            'id' => 'ASC'
        ],
        bool $alternate = true,
        bool $unbuffered = true
    ) {
        $database = $alternate ? $tokenList->database->getAlternateDatabase() : $tokenList->database->getDatabase();
        $table = $tokenList->tableName;
        $query = new DatabaseQuery(
            $database,
            $table,
            $unbuffered
        );
        $limit = min($limit, TokenList::MAX_LIMIT);
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
        parent::__construct($query, Token::class);
    }
}
