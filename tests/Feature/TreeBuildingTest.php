<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Models\LabelRoute;

beforeEach(function (): void {
    LabelRoute::create(['path' => 'electronics', 'depth' => 0]);
    LabelRoute::create(['path' => 'electronics.phones', 'depth' => 1]);
    LabelRoute::create(['path' => 'electronics.phones.iphone', 'depth' => 2]);
    LabelRoute::create(['path' => 'electronics.phones.android', 'depth' => 2]);
    LabelRoute::create(['path' => 'electronics.computers', 'depth' => 1]);
    LabelRoute::create(['path' => 'clothing', 'depth' => 0]);
    LabelRoute::create(['path' => 'clothing.shirts', 'depth' => 1]);
});

describe('toTree', function (): void {
    it('converts flat collection to nested structure', function (): void {
        $routes = LabelRoute::whereDescendantOf('electronics')
            ->orderBy('path')
            ->get();

        // Add root for complete tree
        $routes->push(LabelRoute::where('path', 'electronics')->first());

        $tree = $routes->toTree();

        expect($tree)->toHaveCount(1); // One root
        expect($tree->first()->path)->toBe('electronics');
        expect($tree->first()->children)->toHaveCount(2); // phones, computers
    });

    it('uses custom children key', function (): void {
        $routes = LabelRoute::wherePathMatches('electronics.*')->get();
        $routes->push(LabelRoute::where('path', 'electronics')->first());

        $tree = $routes->toTree(childrenKey: 'items');

        expect($tree->first()->items)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('handles empty collection', function (): void {
        $routes = collect();

        $tree = $routes->toTree();

        expect($tree)->toHaveCount(0);
    });

    it('marks duplicates in DAG structures', function (): void {
        // Create DAG: shared appears under two parents
        LabelRoute::create(['path' => 'gaming', 'depth' => 0]);
        LabelRoute::create(['path' => 'gaming.accessories', 'depth' => 1]);
        // Simulate shared node appearing in results twice
        $routes = collect([
            LabelRoute::where('path', 'electronics.phones.iphone')->first(),
        ]);

        $tree = $routes->toTree();

        // First occurrence is not duplicate
        expect($tree->first()->_is_duplicate)->toBeFalse();
    });

    it('handles multiple roots', function (): void {
        $routes = LabelRoute::whereIsRoot()->get();

        $tree = $routes->toTree();

        expect($tree)->toHaveCount(2);
        expect($tree->pluck('path')->toArray())->toContain('electronics', 'clothing');
    });

    it('rootsOnly filters to depth-0 roots', function (): void {
        // Get a subtree that includes non-root "roots" (top of subset)
        $routes = LabelRoute::whereDescendantOf('electronics')->get();

        $tree = $routes->toTree(rootsOnly: true);

        // Should be empty since none of these are depth-0
        expect($tree)->toHaveCount(0);

        // Add electronics root
        $routes->push(LabelRoute::where('path', 'electronics')->first());
        $tree = $routes->toTree(rootsOnly: true);

        expect($tree)->toHaveCount(1);
        expect($tree->first()->path)->toBe('electronics');
    });
});

describe('ordering scopes', function (): void {
    it('orderByBreadthFirst orders by depth then path', function (): void {
        $routes = LabelRoute::orderByBreadthFirst()->get();

        // Depth 0 first (alphabetical), then depth 1, etc.
        $paths = $routes->pluck('path')->toArray();

        // First should be roots (depth 0)
        expect($paths[0])->toBe('clothing');
        expect($paths[1])->toBe('electronics');
        // Then depth 1
        expect(str_contains($paths[2], '.'))->toBeTrue();
    });

    it('orderByDepthFirst orders by path', function (): void {
        $routes = LabelRoute::orderByDepthFirst()->get();

        // Lexicographic by path gives parent-before-children
        $paths = $routes->pluck('path')->toArray();

        expect($paths)->toBe([
            'clothing',
            'clothing.shirts',
            'electronics',
            'electronics.computers',
            'electronics.phones',
            'electronics.phones.android',
            'electronics.phones.iphone',
        ]);
    });
});

describe('performance', function (): void {
    it('handles large collections efficiently', function (): void {
        // Create 100 routes
        for ($i = 0; $i < 100; $i++) {
            LabelRoute::create(['path' => "perf.level{$i}", 'depth' => 1]);
        }

        $routes = LabelRoute::wherePathMatches('perf.*')->get();
        $routes->push(LabelRoute::create(['path' => 'perf', 'depth' => 0]));

        $start = microtime(true);
        $tree = $routes->toTree();
        $elapsed = (microtime(true) - $start) * 1000;

        expect($elapsed)->toBeLessThan(100); // < 100ms
        expect($tree->first()->children)->toHaveCount(100);
    });
});
