/**
 * Migration: 006_add_client_db_config
 * Description: Adds database configuration columns to clients table for WordPress MySQL connection
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Add database configuration columns to clients table
  const checkColumns = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='clients' AND column_name IN ('wp_db_host', 'wp_db_port', 'wp_db_name', 'wp_db_user', 'wp_db_password', 'wp_table_prefix');
  `);
  
  const existingColumns = checkColumns.rows.map(row => row.column_name);
  
  if (!existingColumns.includes('wp_db_host')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_db_host VARCHAR(255);
    `);
    console.log('  ✓ Added wp_db_host to clients table');
  }
  
  if (!existingColumns.includes('wp_db_port')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_db_port INTEGER DEFAULT 3306;
    `);
    console.log('  ✓ Added wp_db_port to clients table');
  }
  
  if (!existingColumns.includes('wp_db_name')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_db_name VARCHAR(255);
    `);
    console.log('  ✓ Added wp_db_name to clients table');
  }
  
  if (!existingColumns.includes('wp_db_user')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_db_user VARCHAR(255);
    `);
    console.log('  ✓ Added wp_db_user to clients table');
  }
  
  if (!existingColumns.includes('wp_db_password')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_db_password VARCHAR(255);
    `);
    console.log('  ✓ Added wp_db_password to clients table');
  }
  
  if (!existingColumns.includes('wp_table_prefix')) {
    await client.query(`
      ALTER TABLE clients 
      ADD COLUMN wp_table_prefix VARCHAR(50) DEFAULT 'wp_';
    `);
    console.log('  ✓ Added wp_table_prefix to clients table');
  }
};

export const down = async (client) => {
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_table_prefix;');
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_db_password;');
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_db_user;');
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_db_name;');
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_db_port;');
  await client.query('ALTER TABLE clients DROP COLUMN IF EXISTS wp_db_host;');
  console.log('  ✓ Removed database configuration columns from clients table');
};

