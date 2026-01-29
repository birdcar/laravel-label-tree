<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PostgresAdapter implements PathQueryAdapter
{
    protected ?bool $ltreeAvailable = null;

    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        if ($this->hasLtreeSupport()) {
            $lquery = $this->toLquery($pattern);

            return $query->whereRaw("{$column}::lquery ~ ?", [$lquery]);
        }

        $regex = $this->toPostgresRegex($pattern);

        return $query->whereRaw("{$column} ~ ?", [$regex]);
    }

    public function wherePathLike(Builder $query, string $column, string $pattern): Builder
    {
        return $query->where($column, 'LIKE', $pattern);
    }

    public function whereAncestorOf(Builder $query, string $column, string $path): Builder
    {
        if ($this->hasLtreeSupport()) {
            return $query->whereRaw("? <@ {$column}::ltree", [$path]);
        }

        $prefixes = $this->buildPrefixes($path);

        if ($prefixes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $prefixes);
    }

    public function whereDescendantOf(Builder $query, string $column, string $path): Builder
    {
        if ($this->hasLtreeSupport()) {
            return $query->whereRaw("{$column}::ltree <@ ?", [$path]);
        }

        return $query->where($column, 'LIKE', "{$path}.%");
    }

    public function hasLtreeSupport(): bool
    {
        if ($this->ltreeAvailable === null) {
            try {
                $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'ltree'");
                $this->ltreeAvailable = count($result) > 0;
            } catch (\Exception) {
                $this->ltreeAvailable = false;
            }
        }

        return $this->ltreeAvailable;
    }

    protected function toLquery(string $pattern): string
    {
        // In lquery: * matches one label, *{0,} matches zero or more labels
        return str_replace(
            ['**', '*'],
            ['*{0,}', '*'],
            $pattern
        );
    }

    protected function toPostgresRegex(string $pattern): string
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
