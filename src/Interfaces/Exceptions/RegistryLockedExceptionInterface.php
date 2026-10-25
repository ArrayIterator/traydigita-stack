<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Exceptions;

use Psr\Container\ContainerExceptionInterface;

interface RegistryLockedExceptionInterface extends
    LockedExceptionInterface,
    RegistryExceptionInterface,
    ContainerExceptionInterface
{
}
