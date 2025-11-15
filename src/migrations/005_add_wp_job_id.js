/**
 * Migration: 005_add_wp_job_id
 * Description: Adds wp_job_id column to jobs table to link with WordPress jobs
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Add wp_job_id column to jobs table
  const checkWpJobId = await client.query(`
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name='jobs' AND column_name='wp_job_id';
  `);
  
  if (checkWpJobId.rows.length === 0) {
    await client.query(`
      ALTER TABLE jobs 
      ADD COLUMN wp_job_id INTEGER;
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_jobs_wp_job_id ON jobs(wp_job_id, client_id);
    `);
    console.log('  ✓ Added wp_job_id to jobs table');
  }
};

export const down = async (client) => {
  await client.query('DROP INDEX IF EXISTS idx_jobs_wp_job_id;');
  await client.query('ALTER TABLE jobs DROP COLUMN IF EXISTS wp_job_id;');
  console.log('  ✓ Removed wp_job_id from jobs table');
};

