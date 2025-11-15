import pool from '../config/database.js';
import crypto from 'crypto';

/**
 * Middleware to authenticate API key and set tenant context
 */
export const authenticateApiKey = async (req, res, next) => {
  try {
    // Get API key from header or query parameter
    const apiKey = req.headers['x-api-key'] || 
                   req.headers['authorization']?.replace('Bearer ', '') ||
                   req.query.api_key;

    if (!apiKey) {
      return res.status(401).json({ 
        error: 'Unauthorized', 
        message: 'API key is required. Provide it in X-API-Key header or api_key query parameter.' 
      });
    }

    // Find client by API key
    const clientQuery = await pool.query(
      'SELECT id, client_id, name, api_key, is_active FROM clients WHERE api_key = $1',
      [apiKey]
    );

    if (clientQuery.rows.length === 0) {
      return res.status(401).json({ 
        error: 'Unauthorized', 
        message: 'Invalid API key' 
      });
    }

    const client = clientQuery.rows[0];

    if (!client.is_active) {
      return res.status(403).json({ 
        error: 'Forbidden', 
        message: 'Client account is inactive' 
      });
    }

    // Attach client info to request
    req.client = {
      id: client.id,
      clientId: client.client_id,
      name: client.name,
    };

    next();
  } catch (error) {
    console.error('Auth middleware error:', error);
    res.status(500).json({ 
      error: 'Internal server error', 
      message: 'Authentication failed' 
    });
  }
};

/**
 * Generate a secure API key
 */
export const generateApiKey = () => {
  return 'sk_' + crypto.randomBytes(32).toString('hex');
};

/**
 * Generate a client ID
 */
export const generateClientId = (name) => {
  const base = name.toLowerCase().replace(/[^a-z0-9]/g, '-');
  const random = crypto.randomBytes(4).toString('hex');
  return `${base}-${random}`;
};

