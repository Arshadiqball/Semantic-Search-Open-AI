import pool from '../config/database.js';
import { generateApiKey, generateClientId } from '../middleware/authMiddleware.js';

class ClientService {
  /**
   * Create a new client
   */
  async createClient(name, apiUrl = null) {
    try {
      const clientId = generateClientId(name);
      const apiKey = generateApiKey();

      const query = `
        INSERT INTO clients (client_id, name, api_key, api_url)
        VALUES ($1, $2, $3, $4)
        RETURNING *;
      `;

      const result = await pool.query(query, [clientId, name, apiKey, apiUrl]);
      
      return {
        id: result.rows[0].id,
        clientId: result.rows[0].client_id,
        name: result.rows[0].name,
        apiKey: result.rows[0].api_key,
        apiUrl: result.rows[0].api_url,
        isActive: result.rows[0].is_active,
        createdAt: result.rows[0].created_at,
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
          updated_at
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
      const updateFields = [];
      const values = [];
      let paramCount = 1;

      for (const [key, value] of Object.entries(updates)) {
        if (allowedUpdates.includes(key)) {
          updateFields.push(`${key} = $${paramCount}`);
          values.push(value);
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

