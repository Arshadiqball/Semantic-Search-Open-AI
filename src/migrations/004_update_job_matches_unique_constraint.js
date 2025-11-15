/**
 * Migration: 004_update_job_matches_unique_constraint
 * Description: Updates job_matches unique constraint to include client_id for proper multi-tenant isolation
 * Date: 2024-01-XX
 */

export const up = async (client) => {
  // Drop the old unique constraint
  await client.query(`
    ALTER TABLE job_matches 
    DROP CONSTRAINT IF EXISTS job_matches_resume_id_job_id_key;
  `);
  console.log('  ✓ Dropped old unique constraint');

  // Add new unique constraint with client_id
  await client.query(`
    ALTER TABLE job_matches 
    ADD CONSTRAINT job_matches_resume_id_job_id_client_id_key 
    UNIQUE(resume_id, job_id, client_id);
  `);
  console.log('  ✓ Added new unique constraint with client_id');
};

export const down = async (client) => {
  // Drop the new constraint
  await client.query(`
    ALTER TABLE job_matches 
    DROP CONSTRAINT IF EXISTS job_matches_resume_id_job_id_client_id_key;
  `);
  
  // Restore old constraint
  await client.query(`
    ALTER TABLE job_matches 
    ADD CONSTRAINT job_matches_resume_id_job_id_key 
    UNIQUE(resume_id, job_id);
  `);
  
  console.log('  ✓ Restored old unique constraint');
};

