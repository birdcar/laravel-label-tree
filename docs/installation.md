# Installation

## Requirements

- PHP 8.3 or higher
- Laravel 11.0 or higher
- One of: SQLite, PostgreSQL 14+, MySQL 8+

## Install via Composer

```bash
composer require birdcar/laravel-label-tree
```

## Publish Migrations

```bash
php artisan vendor:publish --tag=label-tree-migrations
```

This publishes four migrations:
- `create_labels_table` - The main labels table
- `create_label_relationships_table` - Parent-child relationships
- `create_label_routes_table` - Materialized paths
- `create_labelables_table` - Polymorphic pivot for attaching routes to models

## Run Migrations

```bash
php artisan migrate
```

## Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=label-tree-config
```

This creates `config/label-tree.php` where you can customize table names.

## Verify Installation

```bash
php artisan tinker
>>> use Birdcar\LabelTree\Models\Label;
>>> Label::create(['name' => 'Test']);
```

If you see a Label model returned, installation was successful!

## Alternative: Install Command

You can also use the install command which publishes and runs migrations:

```bash
php artisan label-tree:install
```
