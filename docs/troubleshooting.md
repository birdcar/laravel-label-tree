# Troubleshooting

## Common Issues

### CycleDetectedException

**Error**: "Creating this relationship would form a cycle"

**Cause**: You're trying to create a relationship that would make a label both an ancestor and descendant of another.

**Solution**: Review your hierarchy. DAGs don't allow cycles. If you need bidirectional relationships, use a separate model.

```php
// Check existing relationships
$label->relationships;        // Where this label is parent
$label->reverseRelationships; // Where this label is child
```

### SelfReferenceException

**Error**: "Cannot create self-referential relationship"

**Cause**: Attempting to make a label its own parent.

**Solution**: Use two different labels for parent and child.

### InvalidRouteException

**Error**: "Route not found: {path}"

**Cause**: Trying to attach a route that doesn't exist.

**Solutions**:

1. Create the labels and relationships first:
```php
$root = Label::create(['name' => 'Priority']);
$child = Label::create(['name' => 'High']);
LabelRelationship::create([
    'parent_label_id' => $root->id,
    'child_label_id' => $child->id,
]);
// Now route 'priority.high' exists
```

2. Check if routes need regeneration:
```bash
php artisan label-graph:route:regenerate
```

### RoutesInUseException

**Error**: "Cannot delete relationship: N attachments exist on routes: {paths}"

**Cause**: Trying to delete a relationship when models are attached to affected routes.

**Solutions**:

1. Detach models first:
```php
foreach ($relationship->getAffectedRoutes() as $route) {
    // Handle attachments
}
```

2. Cascade delete (removes attachments):
```php
$relationship->deleteAndCascade();
```

3. Migrate to replacement route:
```php
$relationship->deleteAndReplace('alternative.path');
```

### Routes Not Updating

**Symptoms**: Routes don't reflect recent relationship changes.

**Cause**: Observer may not have fired, or manual database changes.

**Solution**: Regenerate routes manually:
```bash
php artisan label-graph:route:regenerate
```

### Pattern Matching Not Working

**Symptoms**: `wherePathMatches()` returns unexpected results.

**Cause**: Pattern syntax confusion or database adapter issue.

**Debug**:

1. Check the pattern syntax:
   - `*` matches zero or more segments
   - `priority.*` matches `priority`, `priority.high`, `priority.high.critical`

2. Test pattern in tinker:
```php
LabelRoute::wherePathMatches('priority.*')->toSql();
LabelRoute::wherePathMatches('priority.*')->get();
```

3. Try explicit LIKE for comparison:
```php
LabelRoute::wherePathLike('priority%')->get();
```

### SQLite Regex Issues

**Symptoms**: Pattern matching slow or not working in SQLite.

**Cause**: SQLite doesn't have native regex; uses PHP fallback.

**Note**: SQLite adapter is for testing only. Use PostgreSQL or MySQL in production.

## Database Limitations

Different databases have different capabilities and constraints. Understanding these helps you choose the right database and avoid surprises.

### Quick Comparison

| Feature | PostgreSQL | MySQL | SQLite |
|---------|------------|-------|--------|
| **Production ready** | ✅ Recommended | ✅ Yes | ❌ Testing only |
| **ltree/lquery native** | ✅ Yes | ❌ Emulated | ❌ Emulated |
| **Max path length** | ~2048 labels | ~255 chars | ~1000 chars |
| **Max practical depth** | 1000+ | ~50-100 | ~50-100 |
| **Pattern performance** | Excellent | Good | Poor |
| **Array operators** | ✅ Yes | ❌ No | ❌ No |

### PostgreSQL (Recommended)

PostgreSQL with the ltree extension is the optimal choice for production:

- Native lquery pattern matching with GiST index support
- No practical depth limits (tested to 1000+ levels)
- Array operators for batch ancestry queries (`@>`, `<@`)
- Best query performance

**Enable ltree:**
```bash
php artisan label-graph:install-ltree
```

### MySQL

MySQL 8+ works well for most use cases with some constraints:

**Path length limit**: The `path` column is `VARCHAR(255)` by default. With label slugs averaging 10 characters plus dots, practical depth is ~25 levels. For deeper hierarchies:

```php
// In a migration
Schema::table('label_routes', function (Blueprint $table) {
    $table->string('path', 1024)->change();
});
```

**Savepoint nesting**: MySQL has limits on nested transactions. Creating 50+ related LabelRelationships in a single transaction may fail. Use chunked inserts:

```php
// Instead of one loop with 100 creates
foreach (array_chunk($relationships, 25) as $chunk) {
    DB::transaction(function () use ($chunk) {
        foreach ($chunk as $rel) {
            LabelRelationship::create($rel);
        }
    });
}
```

**Pattern matching**: Emulated via LIKE and REGEXP. Slower than PostgreSQL ltree but adequate for most workloads.

### SQLite

**For development/testing only.** Not recommended for production.

- No regex support (PHP fallback, very slow)
- Limited concurrent writes
- No array operators
- Path length limited by page size (~1000 chars practical limit)

### Depth Recommendations by Use Case

| Use Case | Recommended Depth | Database |
|----------|-------------------|----------|
| Flat tags (1-2 levels) | Any | Any |
| Product categories | 5-10 | MySQL/PostgreSQL |
| Issue tracker labels | 3-5 | MySQL/PostgreSQL |
| Deep taxonomies | 20-50 | PostgreSQL |
| File-system-like paths | 50+ | PostgreSQL with ltree |
| Very deep hierarchies | 100+ | PostgreSQL with ltree |

### PostgreSQL-Only Features

These features require PostgreSQL with ltree:

```php
// Array operators - check ancestors/descendants against array
LabelRoute::wherePathInAncestors(['a.b', 'x.y'])->get();
LabelRoute::wherePathInDescendants(['root1', 'root2'])->get();

// Check support at runtime
if (LabelRoute::supportsArrayOperators()) {
    // Use batch operations
}

// Text search patterns (ltxtquery)
LabelRoute::wherePathMatchesText('electronics & wireless')->get();
```

### Migration Between Databases

When migrating from one database to another:

1. **Export routes**: `php artisan label-graph:route:list --format=json > routes.json`
2. **Validate before migration**: `php artisan label-graph:validate`
3. **Check path lengths**: Ensure all paths fit in target column size
4. **Regenerate after migration**: `php artisan label-graph:route:regenerate`

## Validation

Run the validation command to check for issues:

```bash
php artisan label-graph:validate
```

This checks for:
- Cycles in the relationship graph
- Orphaned routes (routes without corresponding relationships)
- Missing routes (relationships without generated routes)
- Invalid references

## Debugging Tips

### View Graph Structure

```bash
php artisan label-graph:visualize
```

### List All Routes

```bash
php artisan label-graph:route:list
```

### Check Relationship Integrity

```php
// Find relationships with missing labels
LabelRelationship::whereDoesntHave('parent')->get();
LabelRelationship::whereDoesntHave('child')->get();
```

### Trace Route Generation

```php
use Birdcar\LabelGraph\Services\RouteGenerator;

$generator = app(RouteGenerator::class);

// Regenerate with logging
DB::enableQueryLog();
$generator->regenerateAll();
dd(DB::getQueryLog());
```

## Getting Help

1. Check the [GitHub issues](https://github.com/birdcar/laravel-label-graph/issues)
2. Run `php artisan label-graph:validate` and include output
3. Include your Laravel and PHP versions
4. Provide minimal reproduction steps
