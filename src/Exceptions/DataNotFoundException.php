<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Exceptions;

use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\NotFoundExceptionInterface;

class DataNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
