/**
 * Migration: 001_initial_schema
 * Description: Creates initial database schema (jobs, resumes, job_matches tables)
 * Date: Initial migration
 */

export const up = async (client) => {
  // Note: pgvector extension creation is handled separately if needed
  // It cannot be created inside a transaction, so we skip it here
  // The extension is optional and the system works without it

  // Create jobs table
  await client.query(`
    CREATE TABLE IF NOT EXISTS jobs (
      id SERIAL PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      company VARCHAR(255) NOT NULL,
      description TEXT NOT NULL,
      required_skills TEXT[] NOT NULL,
      preferred_skills TEXT[],
      experience_years INTEGER,
      location VARCHAR(255),
      salary_range VARCHAR(100),
      employment_type VARCHAR(50),
      embedding JSONB,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
  `);
  console.log('  ✓ Jobs table created');

  // Create resumes table
  await client.query(`
    CREATE TABLE IF NOT EXISTS resumes (
      id SERIAL PRIMARY KEY,
      filename VARCHAR(255) NOT NULL,
      extracted_text TEXT NOT NULL,
      parsed_data JSONB,
      embedding JSONB,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
  `);
  console.log('  ✓ Resumes table created');

  // Create job matches table
  await client.query(`
    CREATE TABLE IF NOT EXISTS job_matches (
      id SERIAL PRIMARY KEY,
      resume_id INTEGER REFERENCES resumes(id) ON DELETE CASCADE,
      job_id INTEGER REFERENCES jobs(id) ON DELETE CASCADE,
      similarity_score FLOAT NOT NULL,
      matched_skills TEXT[],
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(resume_id, job_id)
    );
  `);
  console.log('  ✓ Job matches table created');

  // Create indexes
  await client.query(`
    CREATE INDEX IF NOT EXISTS idx_jobs_id ON jobs(id);
  `);
  await client.query(`
    CREATE INDEX IF NOT EXISTS idx_resumes_id ON resumes(id);
  `);
  await client.query(`
    CREATE INDEX IF NOT EXISTS idx_job_matches_similarity ON job_matches(similarity_score DESC);
  `);
  console.log('  ✓ Indexes created');
};

export const down = async (client) => {
  // Drop indexes
  await client.query('DROP INDEX IF EXISTS idx_job_matches_similarity;');
  await client.query('DROP INDEX IF EXISTS idx_resumes_id;');
  await client.query('DROP INDEX IF EXISTS idx_jobs_id;');
  
  // Drop tables (order matters due to foreign keys)
  await client.query('DROP TABLE IF EXISTS job_matches;');
  await client.query('DROP TABLE IF EXISTS resumes;');
  await client.query('DROP TABLE IF EXISTS jobs;');
  
  console.log('  ✓ Initial schema rolled back');
};

