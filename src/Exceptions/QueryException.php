<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Exceptions;

use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\DatabaseExceptionInterface;

class QueryException extends RuntimeException implements DatabaseExceptionInterface
{
}
