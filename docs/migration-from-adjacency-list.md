# Migration from staudenmeir/laravel-adjacency-list

> Guide for migrating from laravel-adjacency-list to laravel-label-graph.

## Conceptual Differences

| Concept | adjacency-list | label-graph |
|---------|----------------|-------------|
| Data model | Parent ID column | Separate Label + Relationship tables |
| Path storage | Computed at query time | Materialized in LabelRoute table |
| Multi-parent | Graph mode (pivot) | Native (LabelRelationship) |
| Query method | Recursive CTEs | Path prefix matching |

## API Mapping

### Traversal Methods

| adjacency-list | label-graph | Notes |
|----------------|-------------|-------|
| `ancestors()` | `ancestors()` | Same |
| `ancestorsAndSelf()` | `ancestorsAndSelf()` | Same |
| `descendants()` | `descendants()` | Same |
| `descendantsAndSelf()` | `descendantsAndSelf()` | Same |
| `parent()` | `parent()` | Same |
| `children()` | `children()` | Same |
| `siblings()` | `siblings()` | DAG: returns all nodes sharing ANY parent |
| `rootAncestor()` | `rootAncestors()` | Returns collection (DAG can have multiple roots) |
| `bloodline()` | `bloodline()` | Same |

### Query Scopes

| adjacency-list | label-graph | Notes |
|----------------|-------------|-------|
| `isRoot()` | `whereIsRoot()` | Scope version |
| `isLeaf()` | Use `isLeaf()` instance method | No scope equivalent |
| `hasChildren()` | `whereHasChildren()` | Scope version |
| `whereDepth()` | `whereDepth()`, `whereDepthBetween()`, etc. | More options |
| `breadthFirst()` | `orderByBreadthFirst()` | Same |
| `depthFirst()` | `orderByDepthFirst()` | Same |

### Tree Building

| adjacency-list | label-graph | Notes |
|----------------|-------------|-------|
| `->toTree()` | `->toTree()` | Collection macro, handles DAG duplicates |

### Relationship Extensions

| adjacency-list | label-graph | Notes |
|----------------|-------------|-------|
| `hasManyOfDescendants()` | `labelablesOfDescendants()` | Different naming |
| N/A | `whereHasRouteOrDescendant()` | Scope on labeled models |

### Constraint Scoping

| adjacency-list | label-graph | Notes |
|----------------|-------------|-------|
| `withQueryConstraint()` | `withQueryConstraint()` | Same |
| `withInitialQueryConstraint()` | `withInitialConstraint()` | Shorter name |
| `withRecursiveQueryConstraint()` | `withTraversalConstraint()` | Different name |

## Step-by-Step Migration

### 1. Install label-graph alongside adjacency-list

```bash
composer require birdcar/laravel-label-graph
php artisan vendor:publish --tag=label-graph-migrations
php artisan migrate
```

### 2. Create labels from your existing data

```php
// For each unique category in your adjacency-list model
$categories = Category::all();

foreach ($categories as $category) {
    Label::create(['name' => $category->name]);
}
```

### 3. Create relationships

```php
foreach ($categories as $category) {
    if ($category->parent_id) {
        $parentLabel = Label::where('name', $category->parent->name)->first();
        $childLabel = Label::where('name', $category->name)->first();

        LabelRelationship::create([
            'parent_label_id' => $parentLabel->id,
            'child_label_id' => $childLabel->id,
        ]);
    }
}
```

### 4. Migrate model attachments

```php
// If products were in categories
foreach (Product::with('category')->get() as $product) {
    $route = LabelRoute::where('path', 'like', "%{$product->category->slug}")->first();
    $product->attachRoute($route);
}
```

### 5. Update model queries

```php
// Before (adjacency-list)
$products = Product::whereHas('category', fn($q) =>
    $q->whereDescendantOf($rootCategory)
)->get();

// After (label-graph)
$products = Product::whereHasRouteOrDescendant('electronics')->get();
```

### 6. Remove adjacency-list

```bash
composer remove staudenmeir/laravel-adjacency-list
```

## What's Different

### DAG Semantics

label-graph treats everything as a DAG. If you were using adjacency-list's tree mode:

- `siblings()` may return more nodes (any shared parent)
- `rootAncestors()` returns a collection (multiple possible roots)
- Nodes can appear in multiple places in `toTree()` output

### Performance Characteristics

- **Writes are slower**: Route regeneration on relationship changes
- **Reads are faster**: O(1) ancestry checks, no CTEs
- **Pattern queries**: lquery patterns not possible in adjacency-list

### New Capabilities

With label-graph, you gain:

```php
// Pattern matching (not available in adjacency-list)
LabelRoute::wherePathMatches('electronics.*')->get();
LabelRoute::wherePathMatchesText('phone & !case')->get();

// Subtree labelable queries
$route->labelablesOfDescendantsAndSelf(Product::class)->get();

// Multi-subtree queries
Product::whereHasRouteInSubtrees(['electronics', 'accessories'])->get();

// PostgreSQL ltree support for massive hierarchies
```

## Migration Tips

### Gradual Migration

You can run both packages simultaneously during migration:

```php
// Old code still works
$ancestors = $category->ancestors;

// New code in parallel
$labelAncestors = LabelRoute::where('path', $category->path)->first()->ancestors();
```

### Validation

After migration, validate your data:

```bash
php artisan label-graph:validate
php artisan label-graph:visualize --format=ascii
```

### Rollback Plan

Keep adjacency-list installed until you've verified:
- All attachments migrated correctly
- Query results match expectations
- Performance meets requirements

```php
// Compare old vs new
$oldResult = Product::whereHas('category', fn($q) => $q->whereDescendantOf($root))->count();
$newResult = Product::whereHasRouteOrDescendant($rootPath)->count();
assert($oldResult === $newResult);
```
