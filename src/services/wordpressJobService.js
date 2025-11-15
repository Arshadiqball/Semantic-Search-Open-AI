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
      
      let processed = 0;
      let updated = 0;
      let created = 0;

      for (const job of jobs) {
        try {
          // Create text representation for embedding
          const jobText = embeddingService.createJobText(job);
          
          // Generate embedding
          const embedding = await embeddingService.createEmbedding(jobText);

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
            updated++;
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
            created++;
          }
          
          processed++;
          
          if (processed % 10 === 0) {
            console.log(`  → Processed ${processed}/${jobs.length} embeddings...`);
            await new Promise(resolve => setTimeout(resolve, 500));
          }
        } catch (jobError) {
          console.error(`Error processing job ${job.id}:`, jobError);
        }
      }

      console.log(`✅ Processed ${processed} embeddings: ${created} created, ${updated} updated`);
      
      return {
        success: true,
        total: jobs.length,
        processed,
        created,
        updated,
      };
    } catch (error) {
      console.error('Error processing WordPress jobs:', error);
      throw new Error('Failed to process WordPress jobs: ' + error.message);
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

