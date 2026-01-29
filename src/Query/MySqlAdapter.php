<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Illuminate\Database\Eloquent\Builder;

class MySqlAdapter implements PathQueryAdapter
{
    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        $regex = $this->toMysqlRegex($pattern);

        return $query->whereRaw("{$column} REGEXP ?", [$regex]);
    }

    public function wherePathLike(Builder $query, string $column, string $pattern): Builder
    {
        return $query->where($column, 'LIKE', $pattern);
    }

    public function whereAncestorOf(Builder $query, string $column, string $path): Builder
    {
        $prefixes = $this->buildPrefixes($path);

        if ($prefixes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $prefixes);
    }

    public function whereDescendantOf(Builder $query, string $column, string $path): Builder
    {
        return $query->where($column, 'LIKE', "{$path}.%");
    }

    public function hasLtreeSupport(): bool
    {
        return false;
    }

    protected function toMysqlRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');

        // Handle ** at start: **.foo -> (.*\.)? to make prefix optional
        if (str_starts_with($escaped, '\*\*\.')) {
            $escaped = '(.*\.)?'.substr($escaped, 6);
        } elseif ($escaped === '\*\*') {
            return '^.*$';
        }

        // Handle remaining ** (in middle or end)
        $regex = str_replace('\*\*', '.*', $escaped);

        // Handle single * - matches exactly one segment (no dots)
        $regex = str_replace('\*', '[^.]+', $regex);

        return "^{$regex}$";
    }

    /**
     * Build all prefixes of a path (excluding the path itself).
     *
     * @return array<int, string>
     */
    protected function buildPrefixes(string $path): array
    {
        $segments = explode('.', $path);
        $prefixes = [];
        $current = '';

        foreach ($segments as $i => $segment) {
            $current = $i === 0 ? $segment : "{$current}.{$segment}";
            if ($current !== $path) {
                $prefixes[] = $current;
            }
        }

        return $prefixes;
    }
}
