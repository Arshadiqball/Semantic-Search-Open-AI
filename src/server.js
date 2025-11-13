import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import fsp from 'fs/promises';
import https from 'https';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

import pool from './config/database.js';
import pdfExtractor from './services/pdfExtractor.js';
import jobMatchingService from './services/jobMatchingService.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3002;

// ------------------------------------------------------------
// 1. SSL Setup
// ------------------------------------------------------------
// Make sure these files exist (see instructions below)
const SSL_KEY_PATH = '/etc/ssl/private/server.key';
const SSL_CERT_PATH = '/etc/ssl/private/server.crt';

if (!fs.existsSync(SSL_KEY_PATH) || !fs.existsSync(SSL_CERT_PATH)) {
  console.error('❌ SSL certificate or key not found.');
  console.error('Generate with:');
  console.error(`sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ${SSL_KEY_PATH} -out ${SSL_CERT_PATH}`);
  process.exit(1);
}

const httpsOptions = {
  key: fs.readFileSync(SSL_KEY_PATH),
  cert: fs.readFileSync(SSL_CERT_PATH),
};

// ------------------------------------------------------------
// Middleware
// ------------------------------------------------------------
app.use(cors()); // you can restrict later with { origin: "https://atwebtechnologies.com" }
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

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
// Routes
// ------------------------------------------------------------
app.get('/health', (req, res) =>
  res.json({ status: 'ok', timestamp: new Date().toISOString() })
);

app.post('/api/upload-resume', upload.single('resume'), async (req, res) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'No file uploaded' });

    console.log('Processing resume:', req.file.originalname);

    const resumeData = await pdfExtractor.processResume(req.file.path);
    const storedResume = await jobMatchingService.storeResume(
      resumeData,
      req.file.originalname
    );

    const threshold = parseFloat(req.body.threshold) || 0.5;
    const limit = parseInt(req.body.limit) || 10;
    const matches = await jobMatchingService.findMatchingJobs(
      storedResume.id,
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

app.get('/api/resume/:id/matches', async (req, res) => {
  try {
    const resumeId = parseInt(req.params.id);
    if (isNaN(resumeId)) return res.status(400).json({ error: 'Invalid resume ID' });

    const matches = await jobMatchingService.getStoredMatches(resumeId);
    res.json({ success: true, resumeId, matchCount: matches.length, matches });
  } catch (err) {
    console.error('Error getting matches:', err);
    res.status(500).json({ error: 'Failed to get matches', message: err.message });
  }
});

app.get('/api/jobs', async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT id, title, company, description, required_skills, preferred_skills,
             experience_years, location, salary_range, employment_type, created_at
      FROM jobs
      ORDER BY created_at DESC;
    `);
    res.json({ success: true, count: result.rows.length, jobs: result.rows });
  } catch (err) {
    console.error('Error getting jobs:', err);
    res.status(500).json({ error: 'Failed to get jobs', message: err.message });
  }
});

app.get('/api/jobs/:id', async (req, res) => {
  try {
    const jobId = parseInt(req.params.id);
    if (isNaN(jobId)) return res.status(400).json({ error: 'Invalid job ID' });

    const result = await pool.query(
      `SELECT id, title, company, description, required_skills, preferred_skills,
              experience_years, location, salary_range, employment_type, created_at
       FROM jobs WHERE id = $1;`,
      [jobId]
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
// Error handler
// ------------------------------------------------------------
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({ error: 'Internal server error', message: err.message });
});

// ------------------------------------------------------------
// HTTPS Server Start
// ------------------------------------------------------------
https.createServer(httpsOptions, app).listen(PORT, () => {
  console.log(`
╔════════════════════════════════════════════════════════════════════╗
║  Semantic Job Matcher API (HTTPS Enabled)                          ║
║  Server running on: https://54.183.65.104:${PORT}                 ║
║  Environment: ${process.env.NODE_ENV || 'development'}                              ║
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
