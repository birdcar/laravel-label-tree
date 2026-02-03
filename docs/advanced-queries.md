# Advanced Query Patterns

> Advanced patterns for complex hierarchical queries, performance optimization, and constraint scoping.

## Constraint Scoping

Constraints allow you to add conditions to hierarchical queries at different phases.

### Query Constraints

Apply constraints to all parts of a query:

```php
// Filter out archived routes from any traversal
LabelRoute::query()
    ->withQueryConstraint(fn ($q) => $q->where('path', 'not like', '%archived%'))
    ->whereDescendantOf('categories')
    ->get();

// Chain multiple constraints
LabelRoute::query()
    ->withQueryConstraint(fn ($q) => $q->where('path', 'not like', '%draft%'))
    ->withQueryConstraint(fn ($q) => $q->where('depth', '>', 0))
    ->get();
```

### Initial vs Traversal Constraints

For materialized paths, these are conceptually equivalent but provide API clarity:

```php
// Initial constraint: "which starting points to consider"
LabelRoute::query()
    ->withInitialConstraint(fn ($q) => $q->where('path', 'like', 'active%'))
    ->whereIsRoot()
    ->get();

// Traversal constraint: "which results to include"
LabelRoute::query()
    ->whereDescendantOf('products')
    ->withTraversalConstraint(fn ($q) => $q->where('depth', '<', 3))
    ->get();
```

### Instance Methods with Constraints

```php
$route = LabelRoute::where('path', 'electronics')->first();

// Get only active ancestors
$ancestors = $route->ancestorsWithConstraints(
    traversalConstraint: fn ($q) => $q->where('path', 'not like', '%archived%')
);

// Get descendants within depth limit
$descendants = $route->descendantsWithConstraints(
    traversalConstraint: fn ($q) => $q->where('depth', '<=', 3)
);
```

## Subtree Queries for Labeled Models

Query models attached to routes across subtrees:

```php
// Get all products in the "electronics" subtree
$products = $electronics->labelablesOfDescendantsAndSelf(Product::class)->get();

// Check if any tickets exist under "bugs"
$hasBugs = $bugs->hasLabelablesInDescendants(Ticket::class);

// Count articles across descendants
$count = $category->labelablesOfDescendantsCount(Article::class);
```

### HasLabels Scopes

```php
// Find products with route OR any descendant route
Product::whereHasRouteOrDescendant('electronics')->get();

// Find products with route OR any ancestor route
Product::whereHasRouteOrAncestor('electronics.phones.iphone')->get();

// Find products in multiple subtrees
Product::whereHasRouteInSubtrees([
    'electronics.phones',
    'accessories.cases'
])->get();
```

## Tree Building

Convert flat route collections to nested tree structures:

```php
$routes = LabelRoute::whereDescendantOf('categories')->get();
$routes->push(LabelRoute::where('path', 'categories')->first());

// Build tree with default "children" key
$tree = $routes->toTree();

// Custom children key
$tree = $routes->toTree(childrenKey: 'items');

// Only include true root nodes (depth = 0)
$tree = $routes->toTree(rootsOnly: true);
```

### Handling DAG Duplicates

In a DAG, nodes can appear under multiple parents. The `toTree()` macro marks duplicates:

```php
$tree = $routes->toTree();

// Check if a node appeared earlier in the tree
foreach ($tree as $node) {
    if ($node->_is_duplicate) {
        // This node was already shown under another parent
    }
}
```

## Ordering

```php
// Breadth-first: by depth, then alphabetical
LabelRoute::orderByBreadthFirst()->get();
// Result: root1, root2, root1.a, root1.b, root2.a, root1.a.x

// Depth-first: parent before children (alphabetical within siblings)
LabelRoute::orderByDepthFirst()->get();
// Result: root1, root1.a, root1.a.x, root1.b, root2, root2.a
```

## Performance Tips

### Use Specific Patterns

```php
// BETTER: Anchored pattern
LabelRoute::wherePathMatches('priority.*')->get();

// SLOWER: Unanchored pattern
LabelRoute::wherePathMatches('*.priority.*')->get();
```

### Batch Operations

```php
// BETTER: Single query with array
if (LabelRoute::supportsArrayOperators()) {
    LabelRoute::wherePathInDescendants(['a', 'b', 'c'])->get();
}

// SLOWER: Multiple queries
foreach (['a', 'b', 'c'] as $path) {
    LabelRoute::whereDescendantOf($path)->get();
}
```

### PostgreSQL ltree Index

For production with large hierarchies, enable ltree:

```bash
php artisan label-graph:install-ltree
```

Then add GiST indexes in a migration:

```php
Schema::table('label_routes', function (Blueprint $table) {
    $table->ltreeGistIndex('path');
});
```

## Comparison: Materialized Path vs CTEs

| Operation | Materialized Path (label-graph) | Recursive CTE (adjacency-list) |
|-----------|--------------------------------|-------------------------------|
| Get ancestors | O(1) - prefix match | O(depth) - recursive query |
| Get descendants | O(1) - LIKE query | O(n) - recursive traversal |
| Pattern match | O(log n) with lquery | Not supported |
| Check ancestry | O(1) - string compare | Requires traversal |
| Write operations | Slower (route regeneration) | Faster (single row) |

### When to Use Each

**Use label-graph (materialized paths) when:**
- Read-heavy workloads
- Complex pattern matching needed
- Deep hierarchies (100+ levels)
- PostgreSQL with ltree available

**Use adjacency-list when:**
- Write-heavy workloads
- Simple tree structures
- Frequent structure changes
- No pattern matching needs
