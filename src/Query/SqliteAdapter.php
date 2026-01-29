<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Birdcar\LabelTree\Query\Lquery\Lquery;
use Illuminate\Database\Eloquent\Builder;
use PDO;

class SqliteAdapter implements PathQueryAdapter
{
    protected bool $regexpRegistered = false;

    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        $this->ensureRegexpFunction($query);

        $regex = Lquery::toRegex($pattern);

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

    /**
     * Register a custom REGEXP function in SQLite.
     *
     * SQLite doesn't have REGEXP by default - it only recognizes the syntax
     * but throws an error unless you provide an implementation.
     */
    protected function ensureRegexpFunction(Builder $query): void
    {
        if ($this->regexpRegistered) {
            return;
        }

        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            // Register REGEXP function: returns 1 if value matches pattern, 0 otherwise
            $pdo->sqliteCreateFunction('regexp', function (string $pattern, string $value): int {
                return preg_match('/'.$pattern.'/', $value) === 1 ? 1 : 0;
            }, 2);
        }

        $this->regexpRegistered = true;
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
