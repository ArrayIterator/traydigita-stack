<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Actions;

interface ExportableInterface
{
    /**
     * Convert the object into a PHP code string that can be used to recreate the object.
     *
     * @param int $level The current level of recursion (used for indentation).
     * @param int $width The maximum width of the output string.
     * @param int $indent The number of spaces to use for indentation.
     * @param int $maxDepth The maximum depth of recursion allowed.
     * @param array<int, bool> $seen An array to track seen objects to prevent infinite recursion.
     * @return string The PHP code string representation of the object.
     */
    public function export(
        int $level = 0,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 10,
        array $seen = []
    ): string;
}
