import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import multer from 'multer';
import path from 'path';
import fsp from 'fs/promises';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

import pool from './config/database.js';
import pdfExtractor from './services/pdfExtractor.js';
import jobMatchingService from './services/jobMatchingService.js';
import clientService from './services/clientService.js';
import dummyJobService from './services/dummyJobService.js';
import wordpressJobService from './services/wordpressJobService.js';
import { authenticateApiKey } from './middleware/authMiddleware.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// ------------------------------------------------------------
// Middleware
// ------------------------------------------------------------
// CORS configuration for local development
app.use(cors({
    origin: ['http://localhost:8080', 'http://127.0.0.1:8080'],
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-API-Key'],
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Request logging middleware
app.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.path}`);
  console.log(`[Request] Headers:`, {
    'x-api-key': req.headers['x-api-key'] ? req.headers['x-api-key'].substring(0, 10) + '...' : 'missing',
    'content-type': req.headers['content-type'],
    'origin': req.headers['origin'],
  });
  if (req.body && Object.keys(req.body).length > 0) {
    const bodyPreview = JSON.stringify(req.body).substring(0, 200);
    console.log(`[Request] Body preview:`, bodyPreview);
  }
  next();
});

// Trust proxy to get real IP addresses
app.set('trust proxy', true);

// ------------------------------------------------------------
// Ensure uploads directory
// ------------------------------------------------------------
const uploadsDir = path.join(__dirname, '../uploads');
await fsp.mkdir(uploadsDir, { recursive: true });

// ------------------------------------------------------------
// Multer for file uploads
// ------------------------------------------------------------
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, uploadsDir),
  filename: (req, file, cb) => {
    const unique = Date.now() + '-' + Math.round(Math.random() * 1e9);
    cb(null, 'resume-' + unique + path.extname(file.originalname));
  },
});

const upload = multer({
  storage,
  limits: { fileSize: parseInt(process.env.MAX_FILE_SIZE) || 5 * 1024 * 1024 },
  fileFilter: (req, file, cb) => {
    file.mimetype === 'application/pdf'
      ? cb(null, true)
      : cb(new Error('Only PDF files are allowed!'), false);
  },
});

// ------------------------------------------------------------
// Root route (fixes "Cannot GET /")
// ------------------------------------------------------------
app.get('/', (req, res) => {
  res.send(`
    <html>
      <body style="font-family:Arial;background:#0b0b0f;color:#e5e7eb;text-align:center;padding-top:50px;">
        <h2>✅ Semantic Job Matcher API (Local Development)</h2>
        <p>Server is running on port <b>${PORT}</b>.</p>
        <p>Try: <code>/health</code> or <code>/api/upload-resume</code></p>
        <p>Analytics: <a href="/analytics" style="color:#60a5fa;">/analytics</a></p>
      </body>
    </html>
  `);
});

// ------------------------------------------------------------
// Routes
// ------------------------------------------------------------
app.get('/health', (req, res) =>
  res.json({ status: 'ok', timestamp: new Date().toISOString() })
);

// ------------------------------------------------------------
// Client Management Routes (Admin - No Auth Required for Local)
// ------------------------------------------------------------
app.post('/api/admin/clients', async (req, res) => {
  try {
    const { name, apiUrl, db_config, jobs } = req.body;
    if (!name) return res.status(400).json({ error: 'Client name is required' });
    
    // Handle database configuration if provided
    let dbConfig = null;
    if (db_config) {
      dbConfig = {
        db_host: db_config.db_host || db_config.host,
        db_port: db_config.db_port || db_config.port || 3306,
        db_name: db_config.db_name || db_config.database,
        db_user: db_config.db_user || db_config.user,
        db_password: db_config.db_password || db_config.password,
        table_prefix: db_config.table_prefix || db_config.prefix || 'wp_',
      };
      
      // Handle Docker service names
      if (dbConfig.db_host === 'db' || dbConfig.db_host === 'mysql' || dbConfig.db_host === 'mariadb') {
        dbConfig.db_host = 'localhost';
        if (dbConfig.db_port === 3306) {
          dbConfig.db_port = 3307;
        }
      }
    }
    
    // Create client
    const client = await clientService.createClient(name, apiUrl, dbConfig);
    
    // If jobs are provided, store them in Node.js database
    if (jobs && Array.isArray(jobs) && jobs.length > 0) {
      console.log(`[API] Storing ${jobs.length} jobs for client ${client.id} during registration...`);
      try {
        await wordpressJobService.processWordPressJobs(jobs, client.id, pool);
        console.log(`[API] Successfully stored ${jobs.length} jobs for client ${client.id}`);
      } catch (jobError) {
        console.error('[API] Error storing jobs during registration:', jobError);
        // Don't fail registration if jobs fail to store
      }
    }
    
    res.json({ success: true, client });
  } catch (err) {
    console.error('Error creating client:', err);
    res.status(500).json({ error: 'Failed to create client', message: err.message });
  }
});

app.get('/api/admin/clients', async (req, res) => {
  try {
    const clients = await clientService.getAllClients();
    res.json({ success: true, clients });
  } catch (err) {
    console.error('Error getting clients:', err);
    res.status(500).json({ error: 'Failed to get clients', message: err.message });
  }
});

app.get('/api/admin/clients/:id', async (req, res) => {
  try {
    const client = await clientService.getClientById(req.params.id);
    if (!client) return res.status(404).json({ error: 'Client not found' });
    res.json({ success: true, client });
  } catch (err) {
    console.error('Error getting client:', err);
    res.status(500).json({ error: 'Failed to get client', message: err.message });
  }
});

app.put('/api/admin/clients/:id', async (req, res) => {
  try {
    const client = await clientService.updateClient(req.params.id, req.body);
    res.json({ success: true, client });
  } catch (err) {
    console.error('Error updating client:', err);
    res.status(500).json({ error: 'Failed to update client', message: err.message });
  }
});

app.post('/api/admin/clients/:id/regenerate-key', async (req, res) => {
  try {
    const client = await clientService.regenerateApiKey(req.params.id);
    res.json({ success: true, client });
  } catch (err) {
    console.error('Error regenerating API key:', err);
    res.status(500).json({ error: 'Failed to regenerate API key', message: err.message });
  }
});

app.post('/api/admin/clients/:id/generate-dummy-jobs', async (req, res) => {
  try {
    const clientId = parseInt(req.params.id);
    const count = parseInt(req.body.count) || 100;
    
    if (isNaN(clientId)) {
      return res.status(400).json({ error: 'Invalid client ID' });
    }
    
    // Verify client exists
    const client = await clientService.getClientById(clientId);
    if (!client) {
      return res.status(404).json({ error: 'Client not found' });
    }
    
    const result = await dummyJobService.generateDummyJobs(client.id, count);
    res.json(result);
  } catch (err) {
    console.error('Error generating dummy jobs:', err);
    res.status(500).json({ error: 'Failed to generate dummy jobs', message: err.message });
  }
});

// ------------------------------------------------------------
// Client API Routes (Requires API Key Authentication)
// ------------------------------------------------------------
app.post('/api/generate-dummy-jobs', authenticateApiKey, async (req, res) => {
  try {
    const clientId = req.client.id;
    const count = parseInt(req.body.count) || 100;
    
    console.log(`[API] Generate dummy jobs request - Client ID: ${clientId}, Count: ${count}`);
    console.log(`[API] Client info:`, req.client);
    
    if (!clientId) {
      return res.status(400).json({ 
        error: 'Client ID is missing', 
        message: 'Client ID was not found in the authenticated request' 
      });
    }
    
    const result = await dummyJobService.generateDummyJobs(clientId, count);
    console.log(`[API] Successfully generated jobs:`, result);
    res.json(result);
  } catch (err) {
    console.error('[API] Error generating dummy jobs:', err);
    console.error('[API] Error stack:', err.stack);
    res.status(500).json({ 
      error: 'Failed to generate dummy jobs', 
      message: err.message,
      details: process.env.NODE_ENV === 'development' ? err.stack : undefined
    });
  }
});

// Sync jobs from WordPress - WordPress sends jobs directly
app.post('/api/sync-wordpress-jobs', authenticateApiKey, async (req, res) => {
  try {
    console.log(`[API] ========== SYNC WORDPRESS JOBS REQUEST ==========`);
    console.log(`[API] Request received at: ${new Date().toISOString()}`);
    console.log(`[API] Client ID: ${req.client?.id || 'NOT FOUND'}`);
    console.log(`[API] Request body keys:`, Object.keys(req.body || {}));
    
    const clientId = req.client.id;
    const { jobs } = req.body;
    
    console.log(`[API] Sync WordPress jobs request - Client ID: ${clientId}`);
    
    if (!clientId) {
      return res.status(400).json({ 
        error: 'Client ID is missing'
      });
    }
    
    // Validate jobs array
    if (!jobs || !Array.isArray(jobs)) {
      return res.status(400).json({
        error: 'Invalid request',
        message: 'Jobs array is required'
      });
    }
    
    if (jobs.length === 0) {
      return res.json({
        success: true,
        message: 'No jobs to sync',
        total: 0,
        processed: 0,
        created: 0,
        updated: 0,
      });
    }
    
    console.log(`[API] Processing ${jobs.length} jobs from WordPress for client ${clientId}...`);
    console.log(`[API] Sample job data:`, jobs.length > 0 ? JSON.stringify(jobs[0], null, 2) : 'No jobs');
    
    // Process and store jobs in Node.js database
    const result = await wordpressJobService.processWordPressJobs(jobs, clientId, pool);
    
    console.log(`[API] Successfully synced WordPress jobs:`, result);
    res.json({
      success: true,
      ...result
    });
  } catch (err) {
    console.error('[API] Error syncing WordPress jobs:', err);
    console.error('[API] Error details:', {
      message: err.message,
      stack: err.stack,
      name: err.name,
      code: err.code
    });
    
    // Check if it's a database error
    if (err.code === '42P01') {
      console.error('[API] Table does not exist - job_embeddings table might be missing');
      return res.status(500).json({
        error: 'Database table missing',
        message: 'job_embeddings table does not exist. Please run migrations: npm run migrate'
      });
    }
    
    res.status(500).json({
      error: 'Failed to sync WordPress jobs',
      message: err.message,
      originalError: process.env.NODE_ENV === 'development' ? err.message : undefined
    });
  }
});

// ------------------------------------------------------------
// Client API Routes (Requires API Key Authentication)
// ------------------------------------------------------------
app.post('/api/upload-resume', authenticateApiKey, upload.single('resume'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'No file uploaded' });

    const clientId = req.client.id;
    console.log(`[Client: ${req.client.name}] Processing resume:`, req.file.originalname);

    // Extract email and IP address
    let email = null;
    if (req.body.email) {
      email = String(req.body.email).trim();
      if (email === '' || email === 'undefined' || email === 'null') {
        email = null;
      }
    }

    // Extract IP address
    let ipAddress = req.ip || 
                    req.connection?.remoteAddress || 
                    req.headers['x-forwarded-for']?.split(',')[0]?.trim() || 
                    req.socket?.remoteAddress || 
                    req.headers['x-real-ip'] ||
                    'unknown';
    
    if (ipAddress && ipAddress !== 'unknown') {
      ipAddress = ipAddress.replace(/^::ffff:/, '');
    }

    const resumeData = await pdfExtractor.processResume(req.file.path);
    const storedResume = await jobMatchingService.storeResume(
      resumeData,
      req.file.originalname,
      clientId,
      email,
      ipAddress
    );

    const threshold = parseFloat(req.body.threshold) || 0.5;
    const limit = parseInt(req.body.limit) || 10;
    const matches = await jobMatchingService.findMatchingJobs(
      storedResume.id,
      clientId,
      limit,
      threshold
    );

    await fsp.unlink(req.file.path);

    res.json({
      success: true,
      resumeId: storedResume.id,
      extractedSkills: resumeData.parsed.skills,
      experienceYears: resumeData.parsed.experienceYears,
      matchCount: matches.length,
      matches,
    });
  } catch (err) {
    console.error('Error processing resume:', err);
    if (req.file) await fsp.unlink(req.file.path).catch(() => {});
    res.status(500).json({ error: 'Failed to process resume', message: err.message });
  }
});

app.get('/api/resume/:id/matches', authenticateApiKey, async (req, res) => {
  try {
    const resumeId = parseInt(req.params.id);
    if (isNaN(resumeId)) return res.status(400).json({ error: 'Invalid resume ID' });

    const clientId = req.client.id;
    const limit = parseInt(req.query.limit) || 10;
    const matches = await jobMatchingService.getStoredMatches(resumeId, clientId, limit);
    res.json({ success: true, resumeId, matchCount: matches.length, matches });
  } catch (err) {
    console.error('Error getting matches:', err);
    res.status(500).json({ error: 'Failed to get matches', message: err.message });
  }
});

app.get('/api/jobs', authenticateApiKey, async (req, res) => {
  try {
    const clientId = req.client.id;
    const result = await pool.query(`
      SELECT id, title, company, description, required_skills, preferred_skills,
             experience_years, location, salary_range, employment_type, created_at
      FROM jobs
      WHERE client_id = $1
      ORDER BY created_at DESC;
    `, [clientId]);
    res.json({ success: true, count: result.rows.length, jobs: result.rows });
  } catch (err) {
    console.error('Error getting jobs:', err);
    res.status(500).json({ error: 'Failed to get jobs', message: err.message });
  }
});

app.get('/api/jobs/:id', authenticateApiKey, async (req, res) => {
  try {
    const jobId = parseInt(req.params.id);
    if (isNaN(jobId)) return res.status(400).json({ error: 'Invalid job ID' });

    const clientId = req.client.id;
    const result = await pool.query(
      `SELECT id, title, company, description, required_skills, preferred_skills,
              experience_years, location, salary_range, employment_type, created_at
       FROM jobs WHERE id = $1 AND client_id = $2;`,
      [jobId, clientId]
    );

    if (result.rows.length === 0)
      return res.status(404).json({ error: 'Job not found' });

    res.json({ success: true, job: result.rows[0] });
  } catch (err) {
    console.error('Error getting job:', err);
    res.status(500).json({ error: 'Failed to get job', message: err.message });
  }
});

// ------------------------------------------------------------
// Analytics Routes
// ------------------------------------------------------------
app.get('/api/analytics', authenticateApiKey, async (req, res) => {
  try {
    const clientId = req.client.id;
    
    // Get total resume uploads (tenant-aware)
    const totalUploads = await pool.query('SELECT COUNT(*) as count FROM resumes WHERE client_id = $1', [clientId]);
    
    // Get uploads by date
    const uploadsByDate = await pool.query(`
      SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
      FROM resumes
      WHERE client_id = $1
      GROUP BY DATE(created_at)
      ORDER BY date DESC
      LIMIT 30;
    `, [clientId]);
    
    // Get uploads by IP address
    const uploadsByIP = await pool.query(`
      SELECT 
        ip_address,
        COUNT(*) as count,
        MAX(created_at) as last_upload
      FROM resumes
      WHERE ip_address IS NOT NULL AND client_id = $1
      GROUP BY ip_address
      ORDER BY count DESC;
    `, [clientId]);
    
    // Get uploads by email
    const uploadsByEmail = await pool.query(`
      SELECT 
        email,
        COUNT(*) as count,
        MAX(created_at) as last_upload
      FROM resumes
      WHERE email IS NOT NULL AND client_id = $1
      GROUP BY email
      ORDER BY count DESC;
    `, [clientId]);
    
    // Get recent uploads with details
    const recentUploads = await pool.query(`
      SELECT 
        id,
        filename,
        email,
        ip_address,
        created_at,
        (SELECT COUNT(*) FROM job_matches WHERE resume_id = resumes.id AND client_id = $1) as match_count
      FROM resumes
      WHERE client_id = $1
      ORDER BY created_at DESC
      LIMIT 50;
    `, [clientId]);
    
    // Get statistics
    const stats = await pool.query(`
      SELECT 
        COUNT(*) as total_uploads,
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(DISTINCT email) as unique_emails,
        COUNT(CASE WHEN email IS NOT NULL THEN 1 END) as uploads_with_email,
        COUNT(CASE WHEN ip_address IS NOT NULL THEN 1 END) as uploads_with_ip
      FROM resumes
      WHERE client_id = $1;
    `, [clientId]);

    res.json({
      success: true,
      client: req.client.name,
      statistics: stats.rows[0],
      uploadsByDate: uploadsByDate.rows,
      uploadsByIP: uploadsByIP.rows,
      uploadsByEmail: uploadsByEmail.rows,
      recentUploads: recentUploads.rows,
    });
  } catch (err) {
    console.error('Error getting analytics:', err);
    res.status(500).json({ error: 'Failed to get analytics', message: err.message });
  }
});

app.get('/analytics', (req, res) => {
  res.sendFile(path.join(__dirname, '../analytics.html'));
});

app.get('/semantic', (req, res) => {
  res.sendFile(path.join(__dirname, '../semantic-modal.html'));
});

// ------------------------------------------------------------
// Error handler
// ------------------------------------------------------------
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({ error: 'Internal server error', message: err.message });
});

// ------------------------------------------------------------
// HTTP Server Start (Local Development - No SSL)
// ------------------------------------------------------------
app.listen(PORT, () => {
  console.log(`
╔════════════════════════════════════════════════════════════════════╗
║  Semantic Job Matcher API (Local Development - HTTP)                ║
║  Server running on: http://localhost:${PORT}                       ║
║  Environment: ${process.env.NODE_ENV || 'development'}                              ║
║  Analytics: http://localhost:${PORT}/analytics                    ║
╚════════════════════════════════════════════════════════════════════╝
`);
});

// ------------------------------------------------------------
// Graceful Shutdown
// ------------------------------------------------------------
process.on('SIGTERM', async () => {
  console.log('SIGTERM received, closing server...');
  await pool.end();
  process.exit(0);
});
