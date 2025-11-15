/**
 * Migration: 007_create_job_embeddings_table
 * Description: Creates a lightweight table to store only job embeddings (not full job data)
 * Jobs remain in WordPress, only embeddings stored in Node.js for semantic search
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Create job_embeddings table (lightweight - only embeddings, not full job data)
  await client.query(`
    CREATE TABLE IF NOT EXISTS job_embeddings (
      id SERIAL PRIMARY KEY,
      wp_job_id VARCHAR(255) NOT NULL,
      client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
      embedding JSONB NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(wp_job_id, client_id)
    );
  `);
  console.log('  ✓ Job embeddings table created');

  // Create indexes
  await client.query(`
    CREATE INDEX IF NOT EXISTS idx_job_embeddings_client ON job_embeddings(client_id);
  `);
  await client.query(`
    CREATE INDEX IF NOT EXISTS idx_job_embeddings_wp_job_id ON job_embeddings(wp_job_id);
  `);
  console.log('  ✓ Job embeddings indexes created');
};

export const down = async (client) => {
  await client.query('DROP TABLE IF EXISTS job_embeddings CASCADE;');
  console.log('  ✓ Job embeddings table dropped');
};

