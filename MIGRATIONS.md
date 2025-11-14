# Database Migrations Guide

This project uses a custom migration system to manage database schema changes incrementally.

## Overview

Migrations allow you to:
- Apply only new schema changes without recreating the entire database
- Track which migrations have been applied
- Rollback migrations if needed
- Work safely with production databases

## Migration Files

Migration files are located in `src/migrations/` and follow the naming convention:
- `001_initial_schema.js`
- `002_add_analytics_columns.js`
- `003_next_change.js`
- etc.

Each migration file exports two functions:
- `up()` - Applies the migration
- `down()` - Rolls back the migration

## Commands

### Apply All Pending Migrations
```bash
npm run migrate
# or
npm run migrate:up
```

This will:
- Check which migrations have already been applied
- Run only the new/pending migrations
- Track applied migrations in the `schema_migrations` table

### Rollback Last Migration
```bash
npm run migrate:down
```

This will:
- Rollback the most recently applied migration
- Remove it from the tracking table

## Creating a New Migration

1. Create a new file in `src/migrations/` with the next sequential number:
   ```bash
   # Example: 003_add_new_feature.js
   ```

2. Follow this template:
   ```javascript
   /**
    * Migration: 003_add_new_feature
    * Description: Brief description of what this migration does
    * Date: YYYY-MM-DD
    */

   export const up = async (client) => {
     // Your migration code here
     await client.query(`
       ALTER TABLE table_name 
       ADD COLUMN new_column VARCHAR(255);
     `);
     console.log('  ✓ Added new_column to table_name');
   };

   export const down = async (client) => {
     // Rollback code here
     await client.query(`
       ALTER TABLE table_name 
       DROP COLUMN IF EXISTS new_column;
     `);
     console.log('  ✓ Removed new_column from table_name');
   };
   ```

3. Run the migration:
   ```bash
   npm run migrate
   ```

## Migration Tracking

The system automatically creates a `schema_migrations` table to track which migrations have been applied. This table contains:
- `id` - Auto-incrementing ID
- `migration_name` - Name of the migration file (without .js)
- `applied_at` - Timestamp when migration was applied

## Best Practices

1. **Always check if columns/tables exist** before creating them:
   ```javascript
   const check = await client.query(`
     SELECT column_name 
     FROM information_schema.columns 
     WHERE table_name='table_name' AND column_name='column_name';
   `);
   if (check.rows.length === 0) {
     // Add column
   }
   ```

2. **Use transactions** - The migration runner automatically wraps each migration in a transaction, so if it fails, it will rollback.

3. **Test rollbacks** - Always test that your `down()` function works correctly.

4. **Never modify existing migrations** - If you need to change a migration that's already been applied, create a new migration instead.

5. **Use IF EXISTS / IF NOT EXISTS** - This makes migrations idempotent and safe to run multiple times.

## Example: Adding a New Column

```javascript
// src/migrations/003_add_user_preferences.js

export const up = async (client) => {
  // Check if column exists
  const check = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='preferences';
  `);

  if (check.rows.length === 0) {
    await client.query(`
      ALTER TABLE resumes 
      ADD COLUMN preferences JSONB;
    `);
    console.log('  ✓ Added preferences column to resumes table');
  } else {
    console.log('  ⏭️  preferences column already exists');
  }
};

export const down = async (client) => {
  await client.query(`
    ALTER TABLE resumes 
    DROP COLUMN IF EXISTS preferences;
  `);
  console.log('  ✓ Removed preferences column from resumes table');
};
```

## Troubleshooting

### Migration Already Applied
If a migration shows as already applied but you want to re-run it:
1. Manually remove it from `schema_migrations` table:
   ```sql
   DELETE FROM schema_migrations WHERE migration_name = '002_add_analytics_columns';
   ```
2. Then run `npm run migrate` again

### Migration Failed
If a migration fails:
- The transaction will automatically rollback
- Check the error message
- Fix the migration file
- Run `npm run migrate` again

### Check Migration Status
You can check which migrations have been applied:
```sql
SELECT * FROM schema_migrations ORDER BY applied_at;
```

## Initial Setup vs Migrations

- **`npm run init-db`** - Creates the entire database from scratch (use for fresh installs)
- **`npm run migrate`** - Applies only new migrations (use for existing databases)

For production databases, always use `npm run migrate` to apply incremental changes.

