<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

/**
 * @template TName of string
 * @template TResult
 * @template-extends RegistryDefinitionInterface<TName, TResult>
 */
interface RegistryNoChainingInterface extends RegistryDefinitionInterface
{
}
