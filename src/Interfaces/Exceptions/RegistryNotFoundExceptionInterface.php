<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Exceptions;

use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;

interface RegistryNotFoundExceptionInterface extends
    NotFoundExceptionInterface,
    RegistryExceptionInterface,
    PsrNotFoundExceptionInterface
{
}
