<?php

declare(strict_types=1);

use Birdcar\LabelTree\Query\MySqlAdapter;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    $this->adapter = new MySqlAdapter;
});

it('has no ltree support', function (): void {
    expect($this->adapter->hasLtreeSupport())->toBeFalse();
});

it('converts single wildcard pattern to REGEXP', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('path REGEXP ?', ['^[^.]+\\.bug$'])
        ->andReturnSelf();

    $this->adapter->wherePathMatches($query, 'path', '*.bug');
});

it('converts double wildcard at start to REGEXP with optional prefix', function (): void {
    $query = Mockery::mock(Builder::class);
    // **.bug -> (.*\.)?bug - matches "bug", "a.bug", "a.b.bug"
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('path REGEXP ?', ['^(.*\.)?bug$'])
        ->andReturnSelf();

    $this->adapter->wherePathMatches($query, 'path', '**.bug');
});

it('converts pattern with trailing double wildcard to REGEXP', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('path REGEXP ?', ['^priority\\..*$'])
        ->andReturnSelf();

    $this->adapter->wherePathMatches($query, 'path', 'priority.**');
});

it('escapes regex special characters in patterns', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('path REGEXP ?', ['^(.*\.)?test\\(value\\)$'])
        ->andReturnSelf();

    $this->adapter->wherePathMatches($query, 'path', '**.test(value)');
});

it('applies LIKE pattern directly', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.%')
        ->andReturnSelf();

    $this->adapter->wherePathLike($query, 'path', 'priority.%');
});

it('finds ancestors using whereIn with prefixes', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('path', ['a', 'a.b'])
        ->andReturnSelf();

    $this->adapter->whereAncestorOf($query, 'path', 'a.b.c');
});

it('returns empty result for root path ancestors', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereRaw')
        ->once()
        ->with('1 = 0')
        ->andReturnSelf();

    $this->adapter->whereAncestorOf($query, 'path', 'root');
});

it('finds descendants using LIKE prefix', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('path', 'LIKE', 'priority.high.%')
        ->andReturnSelf();

    $this->adapter->whereDescendantOf($query, 'path', 'priority.high');
});
