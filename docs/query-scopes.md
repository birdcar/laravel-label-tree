# Query Scopes

LabelRoute provides powerful query scopes for finding routes by pattern, ancestry, and depth.

## Pattern Matching

Use `wherePathMatches()` with lquery-style patterns:

```php
use Birdcar\LabelTree\Models\LabelRoute;

// Match exact path
LabelRoute::wherePathMatches('priority.high')->get();

// Match path and all descendants (* = zero or more segments)
LabelRoute::wherePathMatches('priority.*')->get();

// Match any path ending with segment
LabelRoute::wherePathMatches('*.critical')->get();

// Match any path containing segment
LabelRoute::wherePathMatches('*.api.*')->get();
```

### Pattern Syntax

| Pattern | Meaning | Examples Matched |
|---------|---------|-----------------|
| `priority` | Exact match | `priority` |
| `priority.*` | Path and descendants | `priority`, `priority.high`, `priority.high.critical` |
| `*.high` | Any ending with "high" | `priority.high`, `area.high` |
| `*` | Zero or more segments | (greedy match) |

### LIKE Patterns

For simple prefix/suffix matching without lquery:

```php
// Starts with
LabelRoute::wherePathLike('priority%')->get();

// Ends with
LabelRoute::wherePathLike('%critical')->get();

// Contains
LabelRoute::wherePathLike('%api%')->get();
```

## Ancestry Queries

### Descendants

Find all routes that descend from a path:

```php
// All routes under "priority" (not including "priority" itself)
LabelRoute::whereDescendantOf('priority')->get();
// Matches: priority.high, priority.high.critical, priority.low
```

### Ancestors

Find all routes that are ancestors of a path:

```php
// All ancestors of "priority.high.critical"
LabelRoute::whereAncestorOf('priority.high.critical')->get();
// Matches: priority, priority.high
```

## Depth Queries

Routes have a `depth` property (0 = root):

```php
// Root labels only
LabelRoute::whereDepth(0)->get();

// Second level
LabelRoute::whereDepth(1)->get();

// Depth range
LabelRoute::whereDepthBetween(1, 3)->get();

// Max depth
LabelRoute::whereDepthLte(2)->get();

// Min depth
LabelRoute::whereDepthGte(1)->get();
```

## Combining Scopes

```php
// Pattern + depth
LabelRoute::wherePathMatches('priority.*')
    ->whereDepthLte(2)
    ->get();

// Multiple patterns (OR)
LabelRoute::where(function ($query) {
    $query->wherePathMatches('priority.*')
          ->orWhere->wherePathMatches('type.*');
})->get();
```

## Database Adapters

Query scopes use database-specific adapters for optimal performance:

| Database | Pattern Matching | Notes |
|----------|-----------------|-------|
| PostgreSQL | Native `~` regex | Best performance |
| MySQL | `REGEXP` function | Good performance |
| SQLite | PHP callback | Works for testing |

The adapter is automatically selected based on your connection.

## Instance Methods

LabelRoute instances also have navigation methods:

```php
$route = LabelRoute::where('path', 'priority.high')->first();

// Collections
$route->ancestors();     // Collection<LabelRoute>
$route->descendants();   // Collection<LabelRoute>
$route->children();      // Collection<LabelRoute> (direct only)

// Single route
$route->parent();        // LabelRoute|null

// Booleans
$route->isRoot();
$route->isLeaf();
$route->isAncestorOf($other);
$route->isDescendantOf($other);
```
