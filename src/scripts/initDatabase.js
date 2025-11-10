import pg from 'pg';
import dotenv from 'dotenv';

dotenv.config();

const { Client } = pg;

async function initDatabase() {
  const client = new Client({
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 5432,
    database: process.env.DB_NAME || 'semantic_job_matcher',
    user: process.env.DB_USER || 'postgres',
    password: process.env.DB_PASSWORD,
  });

  try {
    await client.connect();
    console.log('Connected to database');

    // Try to enable pgvector extension (optional)
    console.log('Checking pgvector extension...');
    try {
      await client.query('CREATE EXTENSION IF NOT EXISTS vector;');
      console.log('✓ pgvector extension enabled');
    } catch (error) {
      console.log('⚠️  pgvector extension not available - will use JSONB for embeddings');
      console.log('   (this is fine, but similarity search will be slower)');
    }

    // Create jobs table
    console.log('Creating jobs table...');
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
    console.log('✓ Jobs table created');

    // Create resumes table
    console.log('Creating resumes table...');
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
    console.log('✓ Resumes table created');

    // Create job matches table
    console.log('Creating job_matches table...');
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
    console.log('✓ Job matches table created');

    // Create indexes for better performance
    console.log('Creating indexes...');
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_jobs_id ON jobs(id);
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_resumes_id ON resumes(id);
    `);
    await client.query(`
      CREATE INDEX IF NOT EXISTS idx_job_matches_similarity ON job_matches(similarity_score DESC);
    `);
    console.log('✓ Indexes created');

    console.log('\n✅ Database initialization completed successfully!');
  } catch (error) {
    console.error('❌ Error initializing database:', error);
    throw error;
  } finally {
    await client.end();
  }
}

// Run initialization
initDatabase().catch(console.error);

