import pool from '../config/database.js';
import { generateApiKey, generateClientId } from '../middleware/authMiddleware.js';

class ClientService {
  /**
   * Create a new client
   * @param {string} name - Client name
   * @param {string} apiUrl - Client API URL
   * @param {Object} dbConfig - WordPress database configuration (optional)
   * @returns {Promise<Object>} Created client
   */
  async createClient(name, apiUrl = null, dbConfig = null) {
    try {
      const clientId = generateClientId(name);
      const apiKey = generateApiKey();

      // Handle database configuration
      let dbHost = null, dbPort = null, dbName = null, dbUser = null, dbPassword = null, tablePrefix = null;
      
      if (dbConfig) {
        dbHost = dbConfig.db_host || dbConfig.host || null;
        dbPort = dbConfig.db_port || dbConfig.port || 3306;
        dbName = dbConfig.db_name || dbConfig.database || null;
        dbUser = dbConfig.db_user || dbConfig.user || null;
        dbPassword = dbConfig.db_password || dbConfig.password || null;
        tablePrefix = dbConfig.table_prefix || dbConfig.prefix || 'wp_';
        
        // Handle Docker service names - convert to localhost for host access
        if (dbHost === 'db' || dbHost === 'mysql' || dbHost === 'mariadb') {
          dbHost = 'localhost';
          // If port is 3306 (internal), use 3307 (Docker mapped port)
          if (dbPort === 3306) {
            dbPort = 3307;
          }
        }
      }

      const query = `
        INSERT INTO clients (
          client_id, name, api_key, api_url,
          wp_db_host, wp_db_port, wp_db_name, wp_db_user, wp_db_password, wp_table_prefix
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)
        RETURNING *;
      `;

      const result = await pool.query(query, [
        clientId, name, apiKey, apiUrl,
        dbHost, dbPort, dbName, dbUser, dbPassword, tablePrefix
      ]);
      
      return {
        id: result.rows[0].id,
        clientId: result.rows[0].client_id,
        name: result.rows[0].name,
        apiKey: result.rows[0].api_key,
        apiUrl: result.rows[0].api_url,
        isActive: result.rows[0].is_active,
        createdAt: result.rows[0].created_at,
        dbConfig: dbHost ? {
          host: result.rows[0].wp_db_host,
          port: result.rows[0].wp_db_port,
          database: result.rows[0].wp_db_name,
          user: result.rows[0].wp_db_user,
          tablePrefix: result.rows[0].wp_table_prefix,
        } : null,
      };
    } catch (error) {
      console.error('Error creating client:', error);
      throw new Error('Failed to create client: ' + error.message);
    }
  }

  /**
   * Get all clients
   */
  async getAllClients() {
    try {
      const query = `
        SELECT 
          id,
          client_id,
          name,
          api_key,
          api_url,
          is_active,
          created_at,
          updated_at,
          (SELECT COUNT(*) FROM resumes WHERE client_id = clients.id) as resume_count,
          (SELECT COUNT(*) FROM jobs WHERE client_id = clients.id) as job_count
        FROM clients
        ORDER BY created_at DESC;
      `;

      const result = await pool.query(query);
      return result.rows.map(row => ({
        id: row.id,
        clientId: row.client_id,
        name: row.name,
        apiKey: row.api_key,
        apiUrl: row.api_url,
        isActive: row.is_active,
        createdAt: row.created_at,
        updatedAt: row.updated_at,
        resumeCount: parseInt(row.resume_count) || 0,
        jobCount: parseInt(row.job_count) || 0,
      }));
    } catch (error) {
      console.error('Error getting clients:', error);
      throw new Error('Failed to get clients: ' + error.message);
    }
  }

  /**
   * Get client by ID
   */
  async getClientById(clientId) {
    try {
      const query = `
        SELECT 
          id,
          client_id,
          name,
          api_key,
          api_url,
          is_active,
          created_at,
          updated_at,
          wp_db_host,
          wp_db_port,
          wp_db_name,
          wp_table_prefix
        FROM clients
        WHERE id = $1 OR client_id = $1;
      `;

      const result = await pool.query(query, [clientId]);
      
      if (result.rows.length === 0) {
        return null;
      }

      const row = result.rows[0];
      return {
        id: row.id,
        clientId: row.client_id,
        name: row.name,
        apiKey: row.api_key,
        apiUrl: row.api_url,
        isActive: row.is_active,
        createdAt: row.created_at,
        updatedAt: row.updated_at,
        hasDbConfig: !!row.wp_db_host,
        dbConfig: row.wp_db_host ? {
          host: row.wp_db_host,
          port: row.wp_db_port,
          database: row.wp_db_name,
          tablePrefix: row.wp_table_prefix,
        } : null,
      };
    } catch (error) {
      console.error('Error getting client:', error);
      throw new Error('Failed to get client: ' + error.message);
    }
  }

  /**
   * Update client
   */
  async updateClient(clientId, updates) {
    try {
      const allowedUpdates = ['name', 'api_url', 'is_active'];
      const dbConfigUpdates = ['wp_db_host', 'wp_db_port', 'wp_db_name', 'wp_db_user', 'wp_db_password', 'wp_table_prefix'];
      const updateFields = [];
      const values = [];
      let paramCount = 1;

      // Handle regular updates
      for (const [key, value] of Object.entries(updates)) {
        if (allowedUpdates.includes(key)) {
          updateFields.push(`${key} = $${paramCount}`);
          values.push(value);
          paramCount++;
        } else if (key === 'db_config' && value) {
          // Handle database configuration update
          const dbConfig = value;
          let dbHost = dbConfig.db_host || dbConfig.host || null;
          let dbPort = dbConfig.db_port || dbConfig.port || 3306;
          const dbName = dbConfig.db_name || dbConfig.database || null;
          const dbUser = dbConfig.db_user || dbConfig.user || null;
          const dbPassword = dbConfig.db_password || dbConfig.password || null;
          const tablePrefix = dbConfig.table_prefix || dbConfig.prefix || 'wp_';
          
          // Handle Docker service names
          if (dbHost === 'db' || dbHost === 'mysql' || dbHost === 'mariadb') {
            dbHost = 'localhost';
            if (dbPort === 3306) {
              dbPort = 3307;
            }
          }
          
          updateFields.push(`wp_db_host = $${paramCount}`);
          values.push(dbHost);
          paramCount++;
          
          updateFields.push(`wp_db_port = $${paramCount}`);
          values.push(dbPort);
          paramCount++;
          
          updateFields.push(`wp_db_name = $${paramCount}`);
          values.push(dbName);
          paramCount++;
          
          updateFields.push(`wp_db_user = $${paramCount}`);
          values.push(dbUser);
          paramCount++;
          
          updateFields.push(`wp_db_password = $${paramCount}`);
          values.push(dbPassword);
          paramCount++;
          
          updateFields.push(`wp_table_prefix = $${paramCount}`);
          values.push(tablePrefix);
          paramCount++;
        }
      }

      if (updateFields.length === 0) {
        throw new Error('No valid fields to update');
      }

      updateFields.push(`updated_at = CURRENT_TIMESTAMP`);
      values.push(clientId);

      const query = `
        UPDATE clients
        SET ${updateFields.join(', ')}
        WHERE id = $${paramCount} OR client_id = $${paramCount}
        RETURNING *;
      `;

      const result = await pool.query(query, values);
      
      if (result.rows.length === 0) {
        throw new Error('Client not found');
      }

      return {
        id: result.rows[0].id,
        clientId: result.rows[0].client_id,
        name: result.rows[0].name,
        apiKey: result.rows[0].api_key,
        apiUrl: result.rows[0].api_url,
        isActive: result.rows[0].is_active,
        updatedAt: result.rows[0].updated_at,
      };
    } catch (error) {
      console.error('Error updating client:', error);
      throw new Error('Failed to update client: ' + error.message);
    }
  }
  
  /**
   * Get client database configuration
   * @param {number|string} clientId - Client ID or client_id
   * @returns {Promise<Object|null>} Database configuration or null
   */
  async getClientDbConfig(clientId) {
    try {
      const query = `
        SELECT wp_db_host, wp_db_port, wp_db_name, wp_db_user, wp_db_password, wp_table_prefix
        FROM clients
        WHERE id = $1 OR client_id = $1;
      `;

      const result = await pool.query(query, [clientId]);
      
      if (result.rows.length === 0 || !result.rows[0].wp_db_host) {
        return null;
      }

      const row = result.rows[0];
      return {
        host: row.wp_db_host,
        port: row.wp_db_port || 3306,
        database: row.wp_db_name,
        user: row.wp_db_user,
        password: row.wp_db_password,
        tablePrefix: row.wp_table_prefix || 'wp_',
      };
    } catch (error) {
      console.error('Error getting client DB config:', error);
      throw new Error('Failed to get client DB config: ' + error.message);
    }
  }

  /**
   * Regenerate API key for a client
   */
  async regenerateApiKey(clientId) {
    try {
      const newApiKey = generateApiKey();

      const query = `
        UPDATE clients
        SET api_key = $1, updated_at = CURRENT_TIMESTAMP
        WHERE id = $2 OR client_id = $2
        RETURNING *;
      `;

      const result = await pool.query(query, [newApiKey, clientId]);
      
      if (result.rows.length === 0) {
        throw new Error('Client not found');
      }

      return {
        id: result.rows[0].id,
        clientId: result.rows[0].client_id,
        name: result.rows[0].name,
        apiKey: result.rows[0].api_key,
      };
    } catch (error) {
      console.error('Error regenerating API key:', error);
      throw new Error('Failed to regenerate API key: ' + error.message);
    }
  }

  /**
   * Delete client (soft delete by setting is_active to false)
   */
  async deleteClient(clientId) {
    try {
      const query = `
        UPDATE clients
        SET is_active = false, updated_at = CURRENT_TIMESTAMP
        WHERE id = $1 OR client_id = $1
        RETURNING *;
      `;

      const result = await pool.query(query, [clientId]);
      
      if (result.rows.length === 0) {
        throw new Error('Client not found');
      }

      return { success: true, message: 'Client deactivated' };
    } catch (error) {
      console.error('Error deleting client:', error);
      throw new Error('Failed to delete client: ' + error.message);
    }
  }
}

export default new ClientService();

