<?php

namespace App\Support;

class Search
{
    /**
     * Build a LIKE pattern in which the user's term is matched literally.
     * Uses "!" as the escape character because both SQLite and MySQL accept
     * `ESCAPE '!'` unchanged (a backslash is not portable between them).
     */
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /** column LIKE ? ESCAPE '!'  — $column must be a trusted identifier, never user input. */
    public static function like($query, string $column, string $term, string $boolean = 'and')
    {
        return $query->whereRaw("{$column} LIKE ? ESCAPE '!'", [static::pattern($term)], $boolean);
    }

    /** Grouped OR across several trusted columns. */
    public static function any($query, array $columns, string $term)
    {
        return $query->where(function ($group) use ($columns, $term) {
            foreach (array_values($columns) as $index => $column) {
                static::like($group, $column, $term, $index === 0 ? 'and' : 'or');
            }
        });
    }
}
