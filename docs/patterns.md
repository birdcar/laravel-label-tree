# Patterns & Anti-Patterns

## Good Patterns

### Flat + Deep Hierarchies

Design your label hierarchies with both organization and specificity:

```php
// Good: Organized and specific
$priority = Label::create(['name' => 'Priority']);
$high = Label::create(['name' => 'High']);
$critical = Label::create(['name' => 'Critical']);

LabelRelationship::create([
    'parent_label_id' => $priority->id,
    'child_label_id' => $high->id,
]);
LabelRelationship::create([
    'parent_label_id' => $high->id,
    'child_label_id' => $critical->id,
]);

// Query all priority-related items
Ticket::whereHasRouteMatching('priority.*')->get();
```

### Multiple Taxonomies

Use separate root labels for different classification systems:

```php
// Good: Independent taxonomies
$priority = Label::create(['name' => 'Priority']);
$type = Label::create(['name' => 'Type']);
$area = Label::create(['name' => 'Area']);

// Each becomes its own hierarchy
// priority.high.critical
// type.bug.regression
// area.api.auth
```

### Attach at Most Specific Level

Attach models to the most specific applicable route:

```php
// Good: Specific attachment
$ticket->attachRoute('priority.high.critical');

// Query broadly or specifically
Ticket::whereHasRouteMatching('priority.*')->get();      // All priority
Ticket::whereHasRoute('priority.high.critical')->get();  // Just critical
```

### Use Patterns for Flexible Queries

```php
// Good: Query by category regardless of specificity
Ticket::whereHasRouteMatching('type.bug.*')->get();

// Good: Find anything ending in a specific label
Ticket::whereHasRouteMatching('*.api')->get();
```

## Anti-Patterns

### Too Flat

Avoid labels without hierarchy - you lose the power of pattern matching:

```php
// Bad: No hierarchy
$ticket->attachRoute('bug');
$ticket->attachRoute('high');
$ticket->attachRoute('api');

// Can't query "all bugs under type" because there's no structure
```

### Too Deep

Avoid excessively deep hierarchies - they're hard to maintain and query:

```php
// Bad: Too deep
// company.engineering.backend.api.auth.oauth.google.workspace

// Better: Flatter with multiple roots
// area.api.auth
// provider.google.workspace
```

### Duplicate Labels in Different Contexts

Avoid reusing the same label name under different parents:

```php
// Confusing: Same label name, different contexts
// type.issue.critical
// priority.high.critical

// Better: Distinct names
// type.issue.showstopper
// priority.critical
```

### Attaching to Non-Leaf Routes

Be intentional about where you attach:

```php
// Potentially confusing
$ticket->attachRoute('priority');        // Root
$ticket->attachRoute('priority.high');   // Mid-level

// When querying 'priority.*', both match
// Is that what you want?

// Usually better: Attach only to leaf nodes
$ticket->attachRoute('priority.high.critical');
```

### Circular Thinking

Remember: DAGs don't allow cycles. Don't try to model bidirectional relationships:

```php
// Won't work: Creates cycle
// depends_on.feature_a → feature_b
// depends_on.feature_b → feature_a

// Model differently: Use a separate relationship table
```

## Performance Patterns

### Eager Load Routes

```php
// Good: Eager load for lists
$tickets = Ticket::withRoutes()->get();

foreach ($tickets as $ticket) {
    echo implode(', ', $ticket->label_paths);
}
```

### Use Count When Appropriate

```php
// Good: Just need count
$tickets = Ticket::withRoutesCount()->get();

foreach ($tickets as $ticket) {
    echo "Labels: {$ticket->label_routes_count}";
}
```

### Batch Operations

```php
// Good: Sync multiple routes at once
$ticket->syncRoutes([
    'priority.high',
    'type.bug',
    'area.api',
]);

// Less efficient: Individual operations
$ticket->attachRoute('priority.high');
$ticket->attachRoute('type.bug');
$ticket->attachRoute('area.api');
```

### Use Depth Constraints

```php
// Good: Limit depth when you don't need full tree
LabelRoute::whereDescendantOf('type')
    ->whereDepthLte(2)
    ->get();
```
