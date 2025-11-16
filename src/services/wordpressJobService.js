import mysql from 'mysql2/promise';
import embeddingService from './embeddingService.js';

/**
 * Service to fetch and process jobs from WordPress client databases
 */
class WordPressJobService {
  /**
   * Connect to a WordPress client's database (MySQL)
   * @param {Object} dbConfig - Database connection config
   * @returns {Promise<mysql.Connection>} Database connection
   */
  async connectToWordPressDB(dbConfig) {
    const connection = await mysql.createConnection({
      host: dbConfig.host || 'localhost',
      port: dbConfig.port || 3306,
      database: dbConfig.database,
      user: dbConfig.user,
      password: dbConfig.password,
    });
    
    return connection;
  }

  /**
   * Fetch jobs from WordPress wp_jobs table
   * @param {Object} dbConfig - WordPress database config
   * @param {string} tablePrefix - WordPress table prefix (default: 'wp_')
   * @returns {Promise<Array>} Array of jobs
   */
  async fetchJobsFromWordPress(dbConfig, tablePrefix = 'wp_') {
    let connection;
    try {
      connection = await this.connectToWordPressDB(dbConfig);
      const tableName = `${tablePrefix}jobs`;
      
      const query = `
        SELECT id, title, company, description, required_skills, preferred_skills,
               experience_years, location, salary_range, employment_type, status
        FROM ?? 
        WHERE status = 'active' OR status IS NULL
        ORDER BY created_at DESC;
      `;
      
      const [rows] = await connection.execute(query, [tableName]);
      
      return rows.map(row => ({
        id: row.id,
        title: row.title,
        company: row.company,
        description: row.description,
        required_skills: row.required_skills 
          ? (Array.isArray(row.required_skills) 
              ? row.required_skills 
              : row.required_skills.split(',').map(s => s.trim()).filter(s => s))
          : [],
        preferred_skills: row.preferred_skills
          ? (Array.isArray(row.preferred_skills)
              ? row.preferred_skills
              : row.preferred_skills.split(',').map(s => s.trim()).filter(s => s))
          : [],
        experience_years: row.experience_years,
        location: row.location,
        salary_range: row.salary_range,
        employment_type: row.employment_type || 'Full-time',
      }));
    } catch (error) {
      console.error('Error fetching jobs from WordPress:', error);
      throw new Error('Failed to fetch jobs from WordPress database: ' + error.message);
    } finally {
      if (connection) {
        await connection.end();
      }
    }
  }

  /**
   * Process WordPress jobs and generate embeddings
   * Stores ONLY embeddings in Node.js (jobs remain in WordPress)
   * @param {Array} jobs - Jobs from WordPress
   * @param {number} clientId - Client ID in Node.js system
   * @param {Object} nodejsPool - Node.js database pool
   * @returns {Promise<Object>} Processing result
   */
  async processWordPressJobs(jobs, clientId, nodejsPool) {
    try {
      console.log(`[WordPress Jobs] Processing ${jobs.length} jobs for client ${clientId}...`);
      console.log(`[WordPress Jobs] Storing only embeddings - jobs remain in WordPress database`);
      
      const BATCH_SIZE = 500; // Process 500 jobs at a time
      let processed = 0;
      let updated = 0;
      let created = 0;

      // Filter and prepare valid jobs
      const validJobs = [];
      for (const job of jobs) {
        if (!job.id) {
          console.error(`⚠️ Skipping job - missing ID:`, job);
          continue;
        }

        if (!job.title && !job.description) {
          console.error(`⚠️ Skipping job ${job.id} - missing title and description`);
          continue;
        }

        const jobText = embeddingService.createJobText(job);
        if (!jobText || jobText.trim().length === 0) {
          console.error(`⚠️ Skipping job ${job.id} - empty job text`);
          continue;
        }

        validJobs.push({ job, jobText });
      }

      console.log(`[WordPress Jobs] Valid jobs to process: ${validJobs.length}/${jobs.length}`);

      // Process jobs in batches
      for (let i = 0; i < validJobs.length; i += BATCH_SIZE) {
        const batch = validJobs.slice(i, i + BATCH_SIZE);
        const batchNumber = Math.floor(i / BATCH_SIZE) + 1;
        const totalBatches = Math.ceil(validJobs.length / BATCH_SIZE);
        
        console.log(`[WordPress Jobs] Processing batch ${batchNumber}/${totalBatches} (${batch.length} jobs)...`);

        try {
          // Prepare job texts for batch embedding
          const jobTexts = batch.map(item => item.jobText);
          
          // Generate embeddings in batch
          const embeddings = await embeddingService.createBatchEmbeddings(jobTexts);

          if (!embeddings || embeddings.length !== batch.length) {
            console.error(`⚠️ Batch embedding mismatch: expected ${batch.length}, got ${embeddings?.length || 0}`);
            // Fallback to individual processing for this batch
            for (let j = 0; j < batch.length; j++) {
              try {
                const embedding = await embeddingService.createEmbedding(batch[j].jobText);
                if (embedding && Array.isArray(embedding) && embedding.length > 0) {
                  await this.saveJobEmbedding(batch[j].job, embedding, clientId, nodejsPool);
                  processed++;
                }
              } catch (err) {
                console.error(`❌ Error processing job ${batch[j].job.id}:`, err);
              }
            }
            continue;
          }

          // Save embeddings in batch
          for (let j = 0; j < batch.length; j++) {
            try {
              const embedding = embeddings[j];
              if (!embedding || !Array.isArray(embedding) || embedding.length === 0) {
                console.error(`⚠️ Skipping job ${batch[j].job.id} - invalid embedding`);
                continue;
              }

              const result = await this.saveJobEmbedding(batch[j].job, embedding, clientId, nodejsPool);
              if (result.created) created++;
              if (result.updated) updated++;
              processed++;
            } catch (jobError) {
              console.error(`❌ Error saving embedding for job ${batch[j].job.id}:`, jobError);
              // Continue with next job
            }
          }

          console.log(`  ✓ Batch ${batchNumber} complete: ${processed}/${validJobs.length} processed`);
        } catch (batchError) {
          console.error(`❌ Error processing batch ${batchNumber}:`, batchError);
          // Fallback to individual processing for this batch
          console.log(`  → Falling back to individual processing for batch ${batchNumber}...`);
          for (const item of batch) {
            try {
              const embedding = await embeddingService.createEmbedding(item.jobText);
              if (embedding && Array.isArray(embedding) && embedding.length > 0) {
                const result = await this.saveJobEmbedding(item.job, embedding, clientId, nodejsPool);
                if (result.created) created++;
                if (result.updated) updated++;
                processed++;
              }
            } catch (err) {
              console.error(`❌ Error processing job ${item.job.id}:`, err);
            }
          }
        }
      }

      console.log(`✅ Processed ${processed} embeddings: ${created} created, ${updated} updated`);

      // --- Delete embeddings for jobs that no longer exist in WordPress ---
      let deleted = 0;
      try {
        const currentWpIds = validJobs.map(v => String(v.job.id));

        if (currentWpIds.length > 0) {
          console.log(`[WordPress Jobs] Pruning embeddings for client ${clientId}. Keeping ${currentWpIds.length} job IDs.`);

          const deleteResult = await nodejsPool.query(
            `
            DELETE FROM job_embeddings
            WHERE client_id = $1
              AND wp_job_id <> ALL($2::text[])
            `,
            [clientId, currentWpIds]
          );

          deleted = deleteResult.rowCount || 0;
          console.log(`[WordPress Jobs] Deleted ${deleted} stale embeddings for client ${clientId}`);
        } else {
          console.log('[WordPress Jobs] No valid jobs provided; skipping deletion of stale embeddings.');
        }
      } catch (pruneError) {
        console.error('[WordPress Jobs] Error pruning stale embeddings:', pruneError);
      }
      
      return {
        success: true,
        total: jobs.length,
        processed,
        created,
        updated,
        deleted,
      };
    } catch (error) {
      console.error('Error processing WordPress jobs:', error);
      throw new Error('Failed to process WordPress jobs: ' + error.message);
    }
  }

  /**
   * Save a single job embedding to the database
   * @param {Object} job - Job object
   * @param {Array} embedding - Embedding vector
   * @param {number} clientId - Client ID
   * @param {Object} nodejsPool - Database pool
   * @returns {Promise<Object>} Result with created/updated flags
   */
  async saveJobEmbedding(job, embedding, clientId, nodejsPool) {
    // Check if embedding already exists (by WordPress job ID)
    const checkQuery = await nodejsPool.query(
      `SELECT id FROM job_embeddings WHERE wp_job_id = $1 AND client_id = $2`,
      [String(job.id), clientId]
    );

    if (checkQuery.rows.length > 0) {
      // Update existing embedding
      await nodejsPool.query(`
        UPDATE job_embeddings SET
          embedding = $1,
          updated_at = CURRENT_TIMESTAMP
        WHERE wp_job_id = $2 AND client_id = $3
      `, [
        JSON.stringify(embedding),
        String(job.id),
        clientId,
      ]);
      return { updated: true, created: false };
    } else {
      // Insert new embedding (only embedding, not full job data)
      await nodejsPool.query(`
        INSERT INTO job_embeddings (
          wp_job_id, client_id, embedding
        )
        VALUES ($1, $2, $3)
      `, [
        String(job.id), // WordPress job ID
        clientId,
        JSON.stringify(embedding),
      ]);
      return { created: true, updated: false };
    }
  }
  
  /**
   * Fetch job details from WordPress database by IDs
   * @param {Object} dbConfig - WordPress database config
   * @param {string} tablePrefix - WordPress table prefix
   * @param {Array<string>} jobIds - Array of WordPress job IDs
   * @returns {Promise<Array>} Array of job objects
   */
  async fetchJobsByIds(dbConfig, tablePrefix, jobIds) {
    if (!jobIds || jobIds.length === 0) {
      return [];
    }
    
    let connection;
    try {
      connection = await this.connectToWordPressDB(dbConfig);
      const tableName = `${tablePrefix}jobs`;
      
      // Create placeholders for IN clause
      const placeholders = jobIds.map(() => '?').join(',');
      
      const query = `
        SELECT id, title, company, description, required_skills, preferred_skills,
               experience_years, location, salary_range, employment_type, status
        FROM ?? 
        WHERE id IN (${placeholders}) AND (status = 'active' OR status IS NULL)
        ORDER BY FIELD(id, ${placeholders});
      `;
      
      const [rows] = await connection.execute(query, [tableName, ...jobIds, ...jobIds]);
      
      return rows.map(row => ({
        id: row.id,
        title: row.title,
        company: row.company,
        description: row.description,
        required_skills: row.required_skills 
          ? (Array.isArray(row.required_skills) 
              ? row.required_skills 
              : row.required_skills.split(',').map(s => s.trim()).filter(s => s))
          : [],
        preferred_skills: row.preferred_skills
          ? (Array.isArray(row.preferred_skills)
              ? row.preferred_skills
              : row.preferred_skills.split(',').map(s => s.trim()).filter(s => s))
          : [],
        experience_years: row.experience_years,
        location: row.location,
        salary_range: row.salary_range,
        employment_type: row.employment_type || 'Full-time',
      }));
    } catch (error) {
      console.error('Error fetching jobs from WordPress:', error);
      throw new Error('Failed to fetch jobs from WordPress database: ' + error.message);
    } finally {
      if (connection) {
        await connection.end();
      }
    }
  }

  /**
   * Sync jobs from WordPress to Node.js server
   * @param {number} clientId - Client ID
   * @param {Object} wpDbConfig - WordPress database configuration
   * @param {string} tablePrefix - WordPress table prefix (default: 'wp_')
   * @param {Object} nodejsPool - Node.js database pool
   * @returns {Promise<Object>} Sync result
   */
  async syncJobsFromWordPress(clientId, wpDbConfig, tablePrefix, nodejsPool) {
    try {
      // Fetch jobs from WordPress
      const jobs = await this.fetchJobsFromWordPress(wpDbConfig, tablePrefix || 'wp_');
      
      if (jobs.length === 0) {
        return {
          success: true,
          message: 'No jobs found in WordPress database',
          total: 0,
          processed: 0,
        };
      }

      // Process and store in Node.js DB
      const result = await this.processWordPressJobs(jobs, clientId, nodejsPool);
      
      return {
        ...result,
        total: jobs.length,
      };
    } catch (error) {
      console.error('Error syncing jobs from WordPress:', error);
      throw error;
    }
  }
}

export default new WordPressJobService();

