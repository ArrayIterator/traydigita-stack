<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

interface RegistrySingletonInterface
{
    /**
     * Get the singleton instance of the registry
     *
     * @return self
     */
    public static function getInstance() : self;
}
