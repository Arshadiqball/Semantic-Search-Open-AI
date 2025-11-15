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
   * Stores embeddings in Node.js server for semantic search
   * @param {Array} jobs - Jobs from WordPress
   * @param {number} clientId - Client ID in Node.js system
   * @param {Object} nodejsPool - Node.js database pool
   * @returns {Promise<Object>} Processing result
   */
  async processWordPressJobs(jobs, clientId, nodejsPool) {
    try {
      console.log(`[WordPress Jobs] Processing ${jobs.length} jobs for client ${clientId}...`);
      
      let processed = 0;
      let updated = 0;
      let created = 0;

      for (const job of jobs) {
        try {
          // Create text representation for embedding
          const jobText = embeddingService.createJobText(job);
          
          // Generate embedding
          const embedding = await embeddingService.createEmbedding(jobText);

          // Check if job already exists in Node.js DB (by WordPress job ID)
          const checkQuery = await nodejsPool.query(
            `SELECT id FROM jobs WHERE wp_job_id = $1 AND client_id = $2`,
            [job.id, clientId]
          );

          if (checkQuery.rows.length > 0) {
            // Update existing job
            await nodejsPool.query(`
              UPDATE jobs SET
                title = $1,
                company = $2,
                description = $3,
                required_skills = $4,
                preferred_skills = $5,
                experience_years = $6,
                location = $7,
                salary_range = $8,
                employment_type = $9,
                embedding = $10,
                updated_at = CURRENT_TIMESTAMP
              WHERE wp_job_id = $11 AND client_id = $12
            `, [
              job.title,
              job.company,
              job.description,
              job.required_skills,
              job.preferred_skills || null,
              job.experience_years,
              job.location,
              job.salary_range,
              job.employment_type,
              JSON.stringify(embedding),
              job.id,
              clientId,
            ]);
            updated++;
          } else {
            // Insert new job
            await nodejsPool.query(`
              INSERT INTO jobs (
                title, company, description, required_skills, preferred_skills,
                experience_years, location, salary_range, employment_type, 
                embedding, client_id, wp_job_id
              )
              VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
            `, [
              job.title,
              job.company,
              job.description,
              job.required_skills,
              job.preferred_skills || null,
              job.experience_years,
              job.location,
              job.salary_range,
              job.employment_type,
              JSON.stringify(embedding),
              clientId,
              job.id, // Store WordPress job ID for reference
            ]);
            created++;
          }
          
          processed++;
          
          if (processed % 10 === 0) {
            console.log(`  → Processed ${processed}/${jobs.length} jobs...`);
            await new Promise(resolve => setTimeout(resolve, 500));
          }
        } catch (jobError) {
          console.error(`Error processing job ${job.id}:`, jobError);
        }
      }

      console.log(`✅ Processed ${processed} jobs: ${created} created, ${updated} updated`);
      
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

