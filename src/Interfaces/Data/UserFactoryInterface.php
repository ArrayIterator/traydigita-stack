<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Data;

interface UserFactoryInterface
{
    /**
     * Find user by id
     *
     * @param int $id
     * @return UserInterface|null
     */
    public function findById(int $id) : ?UserInterface;

    /**
     * Find user by username
     *
     * @param string $username
     * @return UserInterface|null
     */
    public function findByUsername(string $username) : ?UserInterface;

    /**
     * Find user by id or return empty user
     *
     * @param int|string|UserInterface $identity
     * @return UserInterface
     */
    public function findOrEmpty(int|string|UserInterface $identity) : UserInterface;
}
