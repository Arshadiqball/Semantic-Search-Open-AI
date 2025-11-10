import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import multer from 'multer';
import path from 'path';
import fs from 'fs/promises';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

import pool from './config/database.js';
import pdfExtractor from './services/pdfExtractor.js';
import jobMatchingService from './services/jobMatchingService.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Create uploads directory
const uploadsDir = path.join(__dirname, '../uploads');
await fs.mkdir(uploadsDir, { recursive: true });

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, uploadsDir);
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, 'resume-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const upload = multer({
  storage: storage,
  limits: {
    fileSize: parseInt(process.env.MAX_FILE_SIZE) || 5 * 1024 * 1024, // 5MB default
  },
  fileFilter: (req, file, cb) => {
    if (file.mimetype === 'application/pdf') {
      cb(null, true);
    } else {
      cb(new Error('Only PDF files are allowed!'), false);
    }
  }
});

// Routes

/**
 * Health check endpoint
 */
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

/**
 * Upload resume and get job matches
 */
app.post('/api/upload-resume', upload.single('resume'), async (req, res) => {
  try {
    if (!req.file) {
      return res.status(400).json({ error: 'No file uploaded' });
    }

    console.log('Processing resume:', req.file.originalname);

    // Extract text from PDF
    const resumeData = await pdfExtractor.processResume(req.file.path);
    console.log('Extracted skills:', resumeData.parsed.skills);

    // Store resume in database
    const storedResume = await jobMatchingService.storeResume(
      resumeData,
      req.file.originalname
    );
    console.log('Resume stored with ID:', storedResume.id);

    // Find matching jobs
    const threshold = parseFloat(req.body.threshold) || 0.5;
    const limit = parseInt(req.body.limit) || 10;
    
    const matches = await jobMatchingService.findMatchingJobs(
      storedResume.id,
      limit,
      threshold
    );

    console.log(`Found ${matches.length} matching jobs`);

    // Clean up uploaded file
    await fs.unlink(req.file.path);

    res.json({
      success: true,
      resumeId: storedResume.id,
      extractedSkills: resumeData.parsed.skills,
      experienceYears: resumeData.parsed.experienceYears,
      matchCount: matches.length,
      matches: matches,
    });

  } catch (error) {
    console.error('Error processing resume:', error);
    
    // Clean up file on error
    if (req.file) {
      await fs.unlink(req.file.path).catch(console.error);
    }

    res.status(500).json({
      error: 'Failed to process resume',
      message: error.message,
    });
  }
});

/**
 * Get matches for a previously uploaded resume
 */
app.get('/api/resume/:id/matches', async (req, res) => {
  try {
    const resumeId = parseInt(req.params.id);
    
    if (isNaN(resumeId)) {
      return res.status(400).json({ error: 'Invalid resume ID' });
    }

    const matches = await jobMatchingService.getStoredMatches(resumeId);

    res.json({
      success: true,
      resumeId: resumeId,
      matchCount: matches.length,
      matches: matches,
    });

  } catch (error) {
    console.error('Error getting matches:', error);
    res.status(500).json({
      error: 'Failed to get matches',
      message: error.message,
    });
  }
});

/**
 * Get all jobs
 */
app.get('/api/jobs', async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT id, title, company, description, required_skills, preferred_skills,
             experience_years, location, salary_range, employment_type, created_at
      FROM jobs
      ORDER BY created_at DESC;
    `);

    res.json({
      success: true,
      count: result.rows.length,
      jobs: result.rows,
    });

  } catch (error) {
    console.error('Error getting jobs:', error);
    res.status(500).json({
      error: 'Failed to get jobs',
      message: error.message,
    });
  }
});

/**
 * Get specific job by ID
 */
app.get('/api/jobs/:id', async (req, res) => {
  try {
    const jobId = parseInt(req.params.id);
    
    if (isNaN(jobId)) {
      return res.status(400).json({ error: 'Invalid job ID' });
    }

    const result = await pool.query(`
      SELECT id, title, company, description, required_skills, preferred_skills,
             experience_years, location, salary_range, employment_type, created_at
      FROM jobs
      WHERE id = $1;
    `, [jobId]);

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Job not found' });
    }

    res.json({
      success: true,
      job: result.rows[0],
    });

  } catch (error) {
    console.error('Error getting job:', error);
    res.status(500).json({
      error: 'Failed to get job',
      message: error.message,
    });
  }
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    error: 'Internal server error',
    message: process.env.NODE_ENV === 'development' ? err.message : undefined,
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`
╔══════════════════════════════════════════════════════════╗
║   Semantic Job Matcher API Server                       ║
║   Server running on: http://localhost:${PORT}              ║
║   Environment: ${process.env.NODE_ENV || 'development'}                          ║
╚══════════════════════════════════════════════════════════╝

Available endpoints:
  GET  /health                      - Health check
  POST /api/upload-resume           - Upload resume and get matches
  GET  /api/resume/:id/matches      - Get matches for resume
  GET  /api/jobs                    - Get all jobs
  GET  /api/jobs/:id                - Get specific job
  `);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM received, closing server...');
  await pool.end();
  process.exit(0);
});

