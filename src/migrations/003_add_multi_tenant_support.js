/**
 * Migration: 003_add_multi_tenant_support
 * Description: Adds multi-tenant support with clients table and client_id to all tables
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Create clients table
  await client.query(`
    CREATE TABLE IF NOT EXISTS clients (
      id SERIAL PRIMARY KEY,
      client_id VARCHAR(100) UNIQUE NOT NULL,
      name VARCHAR(255) NOT NULL,
      api_key VARCHAR(255) UNIQUE NOT NULL,
      api_url VARCHAR(500),
      is_active BOOLEAN DEFAULT true,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
  `);
  console.log('  ✓ Clients table created');

  // Add client_id to jobs table
  const checkJobsClientId = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='jobs' AND column_name='client_id';
  `);
  
  if (checkJobsClientId.rows.length === 0) {
    await client.query(`
      ALTER TABLE jobs 
      ADD COLUMN client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE;
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_jobs_client_id ON jobs(client_id);
    `);
    console.log('  ✓ Added client_id to jobs table');
  }

  // Add client_id to resumes table
  const checkResumesClientId = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='resumes' AND column_name='client_id';
  `);
  
  if (checkResumesClientId.rows.length === 0) {
    await client.query(`
      ALTER TABLE resumes 
      ADD COLUMN client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE;
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_resumes_client_id ON resumes(client_id);
    `);
    console.log('  ✓ Added client_id to resumes table');
  }

  // Add client_id to job_matches table (through resume_id relationship)
  // Note: job_matches already has resume_id which links to resumes, so client_id is inherited
  // But we can add it directly for faster queries
  const checkMatchesClientId = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='job_matches' AND column_name='client_id';
  `);
  
  if (checkMatchesClientId.rows.length === 0) {
    await client.query(`
      ALTER TABLE job_matches 
      ADD COLUMN client_id INTEGER REFERENCES clients(id) ON DELETE CASCADE;
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_job_matches_client_id ON job_matches(client_id);
    `);
    console.log('  ✓ Added client_id to job_matches table');
  }

  // Create default admin client (for existing data migration)
  const defaultClientCheck = await client.query(`
    SELECT id FROM clients WHERE client_id = 'default';
  `);
  
  if (defaultClientCheck.rows.length === 0) {
    await client.query(`
      INSERT INTO clients (client_id, name, api_key, api_url)
      VALUES ('default', 'Default Client', 'default-api-key-' || gen_random_uuid()::text, NULL);
    `);
    console.log('  ✓ Created default client');
    
    // Update existing records to use default client
    const defaultClient = await client.query(`SELECT id FROM clients WHERE client_id = 'default'`);
    const defaultClientId = defaultClient.rows[0].id;
    
    await client.query(`UPDATE jobs SET client_id = $1 WHERE client_id IS NULL`, [defaultClientId]);
    await client.query(`UPDATE resumes SET client_id = $1 WHERE client_id IS NULL`, [defaultClientId]);
    await client.query(`UPDATE job_matches SET client_id = $1 WHERE client_id IS NULL`, [defaultClientId]);
    console.log('  ✓ Migrated existing data to default client');
  }
};

export const down = async (client) => {
  // Remove client_id columns
  await client.query('ALTER TABLE job_matches DROP COLUMN IF EXISTS client_id;');
  await client.query('ALTER TABLE resumes DROP COLUMN IF EXISTS client_id;');
  await client.query('ALTER TABLE jobs DROP COLUMN IF EXISTS client_id;');
  
  // Drop clients table
  await client.query('DROP TABLE IF EXISTS clients CASCADE;');
  
  console.log('  ✓ Multi-tenant support rolled back');
};

