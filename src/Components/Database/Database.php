<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use SensitiveParameter;

/**
 * @mixin WordPressDatabase
 */
class Database
{
    /**
     * @var WordPressDatabase
     */
    private WordPressDatabase $database;

    /**
     * @var WordPressDatabase
     */
    private WordPressDatabase $alternateDatabase;

    /**
     * Database constructor.
     *
     * @param WordPressDatabase $db
     */
    public function __construct(#[SensitiveParameter] WordPressDatabase $db)
    {
        $this->database = $db;
    }

    /**
     * Get the alternate database instance.
     *
     * @return WordPressDatabase
     */
    public function getAlternateDatabase(): WordPressDatabase
    {
        return $this->alternateDatabase ??= clone $this->database;
    }

    /**
     * Set the alternate database instance.
     *
     * @param WordPressDatabase $alternateDatabase
     */
    public function setAlternateDatabase(#[SensitiveParameter] WordPressDatabase $alternateDatabase): void
    {
        $this->alternateDatabase = $alternateDatabase;
    }

    public function getDatabase(): WordPressDatabase
    {
        return $this->database;
    }

    /**
     * @param WordPressDatabase $database
     */
    public function setDatabase(#[SensitiveParameter] WordPressDatabase $database): void
    {
        $this->database = $database;
    }

    /**
     * Magic method to call a method on the database object.
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments) : mixed
    {
        return $this->getDatabase()->$name(...$arguments);
    }

    /**
     * Magic method to set a property on the database object.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, #[SensitiveParameter] mixed $value): void
    {
        $this->getDatabase()->$name = $value;
    }

    /**
     * Magic method to check if a property is set on the database object.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->getDatabase()->$name);
    }

    /**
     * Magic method to get a property from the database object.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->getDatabase()->$name;
    }
}
