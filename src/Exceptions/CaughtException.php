<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Exceptions;

use ErrorException;
use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;

class CaughtException extends ErrorException implements ExceptionInterface
{
}
