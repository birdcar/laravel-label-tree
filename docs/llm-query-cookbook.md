# Query Cookbook

Comprehensive query pattern reference for laravel-label-graph. Each example includes use case, code, and performance notes.

---

## LabelRoute Queries

### wherePathMatches (lquery patterns)

Match routes using PostgreSQL lquery-style patterns.

**Pattern syntax:**
- `*` - matches zero or more labels
- `{n}` - matches exactly n labels
- `{n,m}` - matches between n and m labels
- `a|b` - matches either a or b

#### Example 1: Wildcard suffix

**Use case:** Find all routes under a category.

```php
<?php

use Birdcar\LabelGraph\Models\LabelRoute;

// Find all priority-related routes
$routes = LabelRoute::wherePathMatches('priority.*')->get();
// Matches: priority, priority.high, priority.high.critical

// Find all routes ending in 'bug'
$bugRoutes = LabelRoute::wherePathMatches('*.bug')->get();
// Matches: type.bug, area.type.bug
```

**Performance:** Fast. Uses index on path column with LIKE prefix matching.

#### Example 2: Wildcard prefix

**Use case:** Find routes by their leaf label regardless of ancestry.

```php
<?php

// Find all 'critical' routes anywhere in hierarchy
$criticalRoutes = LabelRoute::wherePathMatches('*.critical')->get();
// Matches: priority.critical, priority.high.critical, status.critical
```

**Performance:** Moderate. Requires full scan when pattern starts with wildcard.

#### Example 3: Middle wildcard

**Use case:** Find routes with specific start and end.

```php
<?php

// Find routes starting with 'electronics' and ending with 'wireless'
$routes = LabelRoute::wherePathMatches('electronics.*.wireless')->get();
// Matches: electronics.mice.wireless, electronics.keyboards.wireless
```

**Performance:** Moderate. Combines prefix and suffix matching.

#### Example 4: Alternatives

**Use case:** Match multiple specific labels.

```php
<?php

// Find bug OR feature routes
$routes = LabelRoute::wherePathMatches('type.bug|feature')->get();
// Matches: type.bug, type.feature

// Find P0 OR P1 priority
$urgent = LabelRoute::wherePathMatches('priority.p0|p1')->get();
// Matches: priority.p0, priority.p1
```

**Performance:** Fast. Translates to OR conditions.

#### Example 5: Quantifiers

**Use case:** Match routes at specific depths.

```php
<?php

// Find routes exactly 2 levels deep
$routes = LabelRoute::wherePathMatches('*.{2}')->get();
// Matches: priority.high, type.bug, area.frontend

// Find routes 2-3 levels deep
$routes = LabelRoute::wherePathMatches('*.{2,3}')->get();
// Matches: priority.high, priority.high.critical
```

**Performance:** Requires depth calculation. Use `whereDepth()` for better performance.

---

### whereDescendantOf / whereAncestorOf

Query routes by hierarchical position.

#### Example 6: Find all descendants

**Use case:** Get all routes under a category including nested.

```php
<?php

// All routes under 'priority' (not including priority itself)
$descendants = LabelRoute::whereDescendantOf('priority')->get();
// Matches: priority.high, priority.high.critical, priority.low

// Include the parent route itself
$withParent = LabelRoute::whereDescendantOf('priority', includeSelf: true)->get();
// Matches: priority, priority.high, priority.high.critical
```

**Performance:** Fast. Uses path prefix matching with index.

#### Example 7: Find all ancestors

**Use case:** Get the path from root to a specific route.

```php
<?php

// All ancestors of 'priority.high.critical'
$ancestors = LabelRoute::whereAncestorOf('priority.high.critical')->get();
// Matches: priority, priority.high

// Include the route itself (breadcrumb trail)
$breadcrumbs = LabelRoute::whereAncestorOf('priority.high.critical', includeSelf: true)->get();
// Matches: priority, priority.high, priority.high.critical
```

**Performance:** Fast. Iterates through path segments.

---

### whereDepth / whereDepthBetween

Filter routes by hierarchy depth.

#### Example 8: Root routes only

**Use case:** Get top-level categories.

```php
<?php

// Depth 0 = root routes (no parent)
$roots = LabelRoute::whereDepth(0)->get();
// Matches: priority, type, area, electronics, gaming

// First-level children
$firstLevel = LabelRoute::whereDepth(1)->get();
// Matches: priority.high, type.bug, electronics.mice
```

**Performance:** Very fast. Direct column comparison with index.

#### Example 9: Depth range

**Use case:** Get routes within a depth range.

```php
<?php

// Routes 1-2 levels deep (children and grandchildren of roots)
$routes = LabelRoute::whereDepthBetween(1, 2)->get();
// Matches: priority.high, priority.high.critical, type.bug
```

**Performance:** Fast. Range query on indexed column.

---

## HasLabels Model Queries

Queries for models using the `HasLabels` trait.

### whereHasRoute

Exact match on attached route.

#### Example 10: Exact route match

**Use case:** Find models with a specific label.

```php
<?php

use App\Models\Ticket;

// Find tickets labeled exactly 'priority.high.critical'
$criticalTickets = Ticket::whereHasRoute('priority.high.critical')->get();

// Combine with other query conditions
$openCritical = Ticket::query()
    ->whereHasRoute('priority.high.critical')
    ->where('status', 'open')
    ->get();
```

**Performance:** Fast. Uses polymorphic join with exact match.

---

### whereHasRouteMatching

Pattern match on attached routes.

#### Example 11: Find by pattern

**Use case:** Find models matching a label pattern.

```php
<?php

// All tickets with any priority label
$priorityTickets = Ticket::whereHasRouteMatching('priority.*')->get();

// All tickets in any 'bug' category
$bugTickets = Ticket::whereHasRouteMatching('*.bug')->get();

// Tickets with high or critical priority
$urgent = Ticket::whereHasRouteMatching('priority.high|critical')->get();
```

**Performance:** Depends on pattern. Prefix patterns are fast; suffix patterns require more work.

#### Example 12: Multiple patterns

**Use case:** Find models matching any of several patterns.

```php
<?php

// Method 1: Multiple calls (OR logic)
$tickets = Ticket::where(function ($query) {
    $query->whereHasRouteMatching('priority.high.*')
          ->orWhereHasRouteMatching('status.urgent');
})->get();

// Method 2: Alternative pattern
$tickets = Ticket::whereHasRouteMatching('priority.high.*|status.urgent')->get();
```

---

### whereHasRouteDescendantOf / whereHasRouteAncestorOf

Ancestry queries on attached routes.

#### Example 13: Descendants on models

**Use case:** Find models with labels under a category.

```php
<?php

// All tickets under 'priority' category (any priority level)
$tickets = Ticket::whereHasRouteDescendantOf('priority')->get();
// Matches tickets with: priority.high, priority.low, priority.high.critical

// Include exact match
$tickets = Ticket::whereHasRouteDescendantOf('priority', includeSelf: true)->get();
// Also matches tickets with exactly: priority
```

**Performance:** Fast. Uses path prefix in subquery.

#### Example 14: Ancestors on models

**Use case:** Find models whose labels are ancestors of a path.

```php
<?php

// Find tickets that are "above" a specific route
$tickets = Ticket::whereHasRouteAncestorOf('priority.high.critical')->get();
// Matches tickets with: priority, priority.high
```

**Performance:** Moderate. Requires ancestor path calculation.

---

## Route Traversal Methods

Instance methods on LabelRoute for navigating hierarchies.

### Example 15: Ancestor traversal

**Use case:** Get breadcrumb path from root to current route.

```php
<?php

use Birdcar\LabelGraph\Models\LabelRoute;

$route = LabelRoute::where('path', 'electronics.phones.iphone')->first();

// All ancestors (not including self)
$ancestors = $route->ancestors();
// Returns: electronics, electronics.phones

// Ancestors including self, ordered root-to-leaf
$breadcrumbs = $route->ancestorsAndSelf();
// Returns: electronics, electronics.phones, electronics.phones.iphone

// Just the direct parent
$parent = $route->parent();
// Returns: electronics.phones
```

**Performance:** Fast. Path prefix operations.

---

### Example 16: Descendant traversal

**Use case:** Get all items in a category subtree.

```php
<?php

$route = LabelRoute::where('path', 'electronics')->first();

// All descendants
$descendants = $route->descendants();
// Returns: electronics.phones, electronics.phones.iphone, electronics.tablets...

// Descendants including self
$subtree = $route->descendantsAndSelf();

// Just direct children
$children = $route->children();
// Returns: electronics.phones, electronics.tablets (depth +1 only)
```

**Performance:** Fast. LIKE query with index.

---

### Example 17: Sibling and bloodline

**Use case:** Navigate lateral and vertical relationships.

```php
<?php

$route = LabelRoute::where('path', 'electronics.phones')->first();

// Other children of same parent
$siblings = $route->siblings();
// Returns: electronics.tablets, electronics.laptops...

// Including self
$siblingsAndSelf = $route->siblingsAndSelf();

// Complete bloodline (ancestors + self + descendants)
$bloodline = $route->bloodline();
// Returns: electronics, electronics.phones, electronics.phones.iphone...
```

**Performance:** Siblings require parent lookup. Bloodline combines multiple queries.

---

### Example 18: Root and leaf checks

**Use case:** Identify hierarchy boundaries.

```php
<?php

$route = LabelRoute::where('path', 'electronics.phones.iphone')->first();

// Check position in hierarchy
$route->isRoot();        // false - has ancestors
$route->isLeaf();        // true if no children
$route->isAncestorOf('electronics.phones.iphone.pro');   // true
$route->isDescendantOf('electronics');                    // true

// Get all root ancestors (DAG may have multiple roots)
$roots = $route->rootAncestors();
```

---

## Subtree Labelable Queries

Query attached models across subtrees.

### Example 19: Products in category subtree

**Use case:** Find all products under a category including subcategories.

```php
<?php

$electronics = LabelRoute::where('path', 'electronics')->first();

// Products attached to any descendant route
$products = $electronics->labelablesOfDescendants(Product::class)->get();

// Products attached to this route OR any descendant
$products = $electronics->labelablesOfDescendantsAndSelf(Product::class)->get();

// Check if any products exist in subtree
if ($electronics->hasLabelablesInDescendants(Product::class)) {
    // Category has products
}

// Count products across subtree
$count = $electronics->labelablesOfDescendantsCount(Product::class);
```

**Performance:** Uses descendant IDs in subquery. Efficient with proper indexes.

---

### Example 20: Multi-subtree queries

**Use case:** Find models in any of several category trees.

```php
<?php

use App\Models\Product;

// Products in electronics OR accessories subtrees
$products = Product::whereHasRouteInSubtrees([
    'electronics',
    'accessories'
])->get();

// Alternative: explicit OR
$products = Product::where(function ($query) {
    $query->whereHasRouteOrDescendant('electronics')
          ->orWhereHasRouteOrDescendant('accessories');
})->get();
```

---

## Tree Building

Convert flat collections to nested structures.

### Example 21: Build tree from routes

**Use case:** Render hierarchical menu from routes.

```php
<?php

// Get all routes in a subtree
$routes = LabelRoute::whereDescendantOf('categories')->get();
$routes->push(LabelRoute::where('path', 'categories')->first());

// Build nested tree
$tree = $routes->toTree();

// Each node has 'children' array
foreach ($tree as $root) {
    echo $root->path;
    foreach ($root->children as $child) {
        echo "  - {$child->path}";
    }
}

// Custom children key
$tree = $routes->toTree(childrenKey: 'items');

// Only true roots (depth 0)
$tree = $routes->toTree(rootsOnly: true);
```

### Example 22: Handle DAG duplicates

**Use case:** Display tree when nodes have multiple parents.

```php
<?php

$tree = $routes->toTree();

// Nodes appearing under multiple parents are marked
foreach ($tree as $node) {
    renderNode($node);
}

function renderNode($node, $depth = 0) {
    $prefix = str_repeat('  ', $depth);
    $duplicate = $node->_is_duplicate ? ' (also appears elsewhere)' : '';
    echo "{$prefix}{$node->path}{$duplicate}\n";

    foreach ($node->children ?? [] as $child) {
        renderNode($child, $depth + 1);
    }
}
```

---

## Ordering

### Example 23: Breadth-first and depth-first

**Use case:** Control traversal order for display.

```php
<?php

// Breadth-first: level by level
$routes = LabelRoute::orderByBreadthFirst()->get();
// root, child1, child2, child1.a, child1.b, child2.a...

// Depth-first: parent before children (tree order)
$routes = LabelRoute::orderByDepthFirst()->get();
// root, child1, child1.a, child1.b, child2, child2.a...
```

---

## Constraint Scoping

### Example 24: Filter traversals

**Use case:** Exclude archived routes from queries.

```php
<?php

// Query constraint: applies to all results
$routes = LabelRoute::query()
    ->withQueryConstraint(fn ($q) => $q->where('path', 'not like', '%archived%'))
    ->whereDescendantOf('categories')
    ->get();

// Instance method with constraints
$route = LabelRoute::where('path', 'categories')->first();

$filteredAncestors = $route->ancestorsWithConstraints(
    traversalConstraint: fn ($q) => $q->where('depth', '>', 0)
);

$limitedDescendants = $route->descendantsWithConstraints(
    traversalConstraint: fn ($q) => $q->where('depth', '<=', 3)
);
```

---

## Advanced Patterns

### Combining Scopes

#### Example 25: Multiple label conditions

**Use case:** Complex filtering by multiple labels.

```php
<?php

// Critical bugs in frontend area
$tickets = Ticket::query()
    ->whereHasRouteMatching('priority.high.*')  // Any high priority
    ->whereHasRoute('type.bug')                  // Exact bug type
    ->whereHasRouteMatching('area.frontend.*')  // Any frontend area
    ->get();
```

#### Example 26: Label + model conditions

**Use case:** Filter by labels AND model attributes.

```php
<?php

// Open critical tickets assigned to specific user
$tickets = Ticket::query()
    ->whereHasRoute('priority.high.critical')
    ->where('status', 'open')
    ->where('assigned_to', $userId)
    ->orderBy('created_at', 'desc')
    ->get();
```

---

### Subqueries

#### Example 27: Models with specific label count

**Use case:** Find models with exactly N labels.

```php
<?php

// Tickets with exactly 3 labels
$tickets = Ticket::withRoutesCount()
    ->having('label_routes_count', '=', 3)
    ->get();

// Tickets with more than 5 labels (over-categorized?)
$overLabeled = Ticket::withRoutesCount()
    ->having('label_routes_count', '>', 5)
    ->get();
```

#### Example 28: Models without any labels

**Use case:** Find uncategorized items.

```php
<?php

// Tickets missing labels
$unlabeled = Ticket::whereDoesntHave('labelRoutes')->get();

// Alternative with count
$unlabeled = Ticket::withRoutesCount()
    ->having('label_routes_count', '=', 0)
    ->get();
```

---

### Performance Optimization

#### Example 29: Eager loading

**Use case:** Avoid N+1 queries when displaying labels.

```php
<?php

// Bad: N+1 queries
$tickets = Ticket::all();
foreach ($tickets as $ticket) {
    echo $ticket->label_paths; // Queries for each ticket
}

// Good: Eager load
$tickets = Ticket::withRoutes()->get();
foreach ($tickets as $ticket) {
    echo $ticket->label_paths; // No additional queries
}
```

#### Example 30: Selective loading

**Use case:** Load only specific route data.

```php
<?php

// Load routes with their labels (for display)
$tickets = Ticket::with(['labelRoutes.label'])->get();
foreach ($tickets as $ticket) {
    foreach ($ticket->labelRoutes as $route) {
        echo $route->label->name; // Label model available
    }
}
```

---

## Query Summary Table

### Model Scopes (HasLabels trait)

| Query | Use Case | Performance |
|-------|----------|-------------|
| `whereHasRoute('exact')` | Exact label match | Fast |
| `whereHasRouteMatching('prefix.*')` | Pattern with prefix | Fast |
| `whereHasRouteMatching('*.suffix')` | Pattern with suffix | Moderate |
| `whereHasRouteDescendantOf('path')` | Descendants only | Fast |
| `whereHasRouteAncestorOf('path')` | Ancestors only | Moderate |
| `whereHasRouteOrDescendant('path')` | Path + descendants | Fast |
| `whereHasRouteOrAncestor('path')` | Path + ancestors | Moderate |
| `whereHasRouteInSubtrees([...])` | Multiple subtrees | Fast |

### LabelRoute Scopes

| Query | Use Case | Performance |
|-------|----------|-------------|
| `whereDepth(n)` | Specific depth | Very Fast |
| `whereDepthBetween(min, max)` | Depth range | Very Fast |
| `whereIsRoot()` | Root routes | Very Fast |
| `whereHasChildren()` | Non-leaf routes | Moderate |
| `wherePathMatches('pattern')` | lquery patterns | Varies |
| `whereDescendantOf('path')` | Subtree query | Fast |
| `whereAncestorOf('path')` | Ancestor query | Fast |
| `orderByBreadthFirst()` | Level order | Fast |
| `orderByDepthFirst()` | Tree order | Fast |

### Instance Methods

| Method | Returns | Performance |
|--------|---------|-------------|
| `ancestors()` | Collection | Fast |
| `ancestorsAndSelf()` | Collection (ordered) | Fast |
| `descendants()` | Collection | Fast |
| `descendantsAndSelf()` | Collection | Fast |
| `parent()` | LabelRoute\|null | Fast |
| `children()` | Collection | Fast |
| `siblings()` | Collection | Moderate |
| `rootAncestors()` | Collection | Fast |
| `bloodline()` | Collection | Moderate |
| `labelablesOfDescendants(Class)` | Builder | Moderate |

---

*Use these patterns as building blocks. Combine them for complex queries.*
