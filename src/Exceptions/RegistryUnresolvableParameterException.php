<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Exceptions;

use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\RegistryUnresolvableParameterExceptionInterface as E;

class RegistryUnresolvableParameterException extends RuntimeException implements E
{
}
