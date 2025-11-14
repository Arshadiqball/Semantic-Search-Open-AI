import pg from 'pg';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

dotenv.config();

const { Client } = pg;

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/**
 * Get all migration files sorted by name
 */
function getMigrationFiles() {
  const migrationsDir = path.join(__dirname, '../migrations');
  if (!fs.existsSync(migrationsDir)) {
    return [];
  }
  
  return fs.readdirSync(migrationsDir)
    .filter(file => file.endsWith('.js'))
    .sort();
}

/**
 * Create migrations tracking table if it doesn't exist
 */
async function ensureMigrationsTable(client) {
  await client.query(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      id SERIAL PRIMARY KEY,
      migration_name VARCHAR(255) UNIQUE NOT NULL,
      applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
  `);
}

/**
 * Get list of applied migrations
 */
async function getAppliedMigrations(client) {
  const result = await client.query(
    'SELECT migration_name FROM schema_migrations ORDER BY migration_name;'
  );
  return result.rows.map(row => row.migration_name);
}

/**
 * Mark migration as applied
 */
async function markMigrationApplied(client, migrationName) {
  await client.query(
    'INSERT INTO schema_migrations (migration_name) VALUES ($1) ON CONFLICT (migration_name) DO NOTHING;',
    [migrationName]
  );
}

/**
 * Mark migration as rolled back
 */
async function markMigrationRolledBack(client, migrationName) {
  await client.query(
    'DELETE FROM schema_migrations WHERE migration_name = $1;',
    [migrationName]
  );
}

/**
 * Run migrations
 */
async function runMigrations(direction = 'up') {
  const client = new Client({
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 5432,
    database: process.env.DB_NAME || 'semantic_job_matcher',
    user: process.env.DB_USER || 'postgres',
    password: process.env.DB_PASSWORD,
  });

  try {
    await client.connect();
    console.log('✓ Connected to database\n');

    // Try to enable pgvector extension (optional, must be outside transaction)
    try {
      await client.query('CREATE EXTENSION IF NOT EXISTS vector;');
      console.log('✓ pgvector extension enabled (if available)\n');
    } catch (error) {
      console.log('⚠️  pgvector extension not available - will use JSONB for embeddings\n');
    }

    // Ensure migrations table exists
    await ensureMigrationsTable(client);

    // Get migration files
    const migrationFiles = getMigrationFiles();
    
    if (migrationFiles.length === 0) {
      console.log('⚠️  No migration files found in migrations directory');
      return;
    }

    if (direction === 'up') {
      // Get applied migrations
      const appliedMigrations = await getAppliedMigrations(client);
      
      console.log(`📋 Found ${migrationFiles.length} migration file(s)`);
      console.log(`📋 ${appliedMigrations.length} migration(s) already applied\n`);

      // Run pending migrations
      let appliedCount = 0;
      for (const file of migrationFiles) {
        const migrationName = file.replace('.js', '');
        
        if (appliedMigrations.includes(migrationName)) {
          console.log(`⏭️  Skipping ${migrationName} (already applied)`);
          continue;
        }

        console.log(`🔄 Running migration: ${migrationName}`);
        
        try {
          // Import migration module
          const migrationPath = path.join(__dirname, '../migrations', file);
          const migration = await import(`file://${migrationPath}`);
          
          if (!migration.up) {
            throw new Error(`Migration ${migrationName} does not export an 'up' function`);
          }

          // Start transaction
          await client.query('BEGIN');
          
          try {
            // Run migration
            await migration.up(client);
            
            // Mark as applied
            await markMigrationApplied(client, migrationName);
            
            // Commit transaction
            await client.query('COMMIT');
            
            console.log(`✅ Migration ${migrationName} applied successfully\n`);
            appliedCount++;
          } catch (error) {
            // Rollback transaction
            await client.query('ROLLBACK');
            throw error;
          }
        } catch (error) {
          console.error(`❌ Error running migration ${migrationName}:`, error.message);
          throw error;
        }
      }

      if (appliedCount === 0) {
        console.log('✅ All migrations are up to date!');
      } else {
        console.log(`\n✅ Successfully applied ${appliedCount} migration(s)`);
      }
    } else if (direction === 'down') {
      // Rollback last migration
      const appliedMigrations = await getAppliedMigrations(client);
      
      if (appliedMigrations.length === 0) {
        console.log('⚠️  No migrations to rollback');
        return;
      }

      // Get last applied migration
      const lastMigration = appliedMigrations[appliedMigrations.length - 1];
      const migrationFile = migrationFiles.find(f => f.replace('.js', '') === lastMigration);
      
      if (!migrationFile) {
        console.log(`⚠️  Migration file for ${lastMigration} not found`);
        return;
      }

      console.log(`🔄 Rolling back migration: ${lastMigration}`);
      
      try {
        // Import migration module
        const migrationPath = path.join(__dirname, '../migrations', migrationFile);
        const migration = await import(`file://${migrationPath}`);
        
        if (!migration.down) {
          throw new Error(`Migration ${lastMigration} does not export a 'down' function`);
        }

        // Start transaction
        await client.query('BEGIN');
        
        try {
          // Run rollback
          await migration.down(client);
          
          // Mark as rolled back
          await markMigrationRolledBack(client, lastMigration);
          
          // Commit transaction
          await client.query('COMMIT');
          
          console.log(`✅ Migration ${lastMigration} rolled back successfully\n`);
        } catch (error) {
          // Rollback transaction
          await client.query('ROLLBACK');
          throw error;
        }
      } catch (error) {
        console.error(`❌ Error rolling back migration ${lastMigration}:`, error.message);
        throw error;
      }
    }

  } catch (error) {
    console.error('❌ Migration error:', error);
    process.exit(1);
  } finally {
    await client.end();
  }
}

// Get command line argument
const direction = process.argv[2] === 'down' ? 'down' : 'up';

// Run migrations
runMigrations(direction).catch(console.error);

