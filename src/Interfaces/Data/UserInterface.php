<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Data;

interface UserInterface
{
    /**
     * Get user Id
     *
     * @return int
     */
    public function getId() : int;

    /**
     * Get user username
     *
     * @return string
     */
    public function getUsername() : string;
}
