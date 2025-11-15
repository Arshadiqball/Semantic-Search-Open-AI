import pool from '../config/database.js';
import embeddingService from './embeddingService.js';
import wordpressJobService from './wordpressJobService.js';
import clientService from './clientService.js';

class JobMatchingService {
  /**
   * Check if similar resume already exists (tenant-aware)
   * @param {string} extractedText - Resume text
   * @param {number} clientId - Client ID for tenant isolation
   * @returns {Promise<Object|null>} Existing resume or null
   */
  async findExistingResume(extractedText, clientId) {
    try {
      // Create a simple hash of the text for comparison
      const textHash = extractedText.substring(0, 100).trim();
      
      const query = `
        SELECT id, filename, extracted_text, parsed_data, embedding, email, client_id, created_at
        FROM resumes
        WHERE LEFT(extracted_text, 100) = $1
          AND client_id = $2
        ORDER BY created_at DESC
        LIMIT 1;
      `;
      
      const result = await pool.query(query, [textHash, clientId]);
      
      if (result.rows.length > 0) {
        const existing = result.rows[0];
        // Double check full text matches (first 1000 chars)
        if (existing.extracted_text.substring(0, 1000) === extractedText.substring(0, 1000)) {
          return existing;
        }
      }
      
      return null;
    } catch (error) {
      console.error('Error checking existing resume:', error);
      return null; // On error, proceed with new resume
    }
  }

  /**
   * Check if resume with same text AND email already exists (tenant-aware)
   * @param {string} extractedText - Resume text
   * @param {string} email - User email
   * @param {number} clientId - Client ID for tenant isolation
   * @returns {Promise<Object|null>} Existing resume or null
   */
  async findExistingResumeByEmail(extractedText, email, clientId) {
    try {
      if (!email) return null; // Can't match by email if no email provided
      
      const textHash = extractedText.substring(0, 100).trim();
      
      const query = `
        SELECT id, filename, extracted_text, parsed_data, embedding, email, ip_address, client_id, created_at
        FROM resumes
        WHERE LEFT(extracted_text, 100) = $1
          AND email = $2
          AND client_id = $3
        ORDER BY created_at DESC
        LIMIT 1;
      `;
      
      const result = await pool.query(query, [textHash, email, clientId]);
      
      if (result.rows.length > 0) {
        const existing = result.rows[0];
        // Double check full text matches (first 1000 chars)
        if (existing.extracted_text.substring(0, 1000) === extractedText.substring(0, 1000)) {
          return existing;
        }
      }
      
      return null;
    } catch (error) {
      console.error('Error checking existing resume by email:', error);
      return null;
    }
  }

  /**
   * Store resume in database with embedding (tenant-aware)
   * @param {Object} resumeData - Resume data from PDF extractor
   * @param {string} filename - Original filename
   * @param {number} clientId - Client ID for tenant isolation
   * @param {string} email - User email address
   * @param {string} ipAddress - User IP address
   * @returns {Promise<Object>} Stored resume record
   */
  async storeResume(resumeData, filename, clientId, email = null, ipAddress = null) {
    try {
      // FIRST: Check if same resume + same email exists (STRICT CACHE - NO OpenAI calls)
      if (email) {
        console.log('🔍 Checking if resume with same email already exists...');
        const existingResumeByEmail = await this.findExistingResumeByEmail(resumeData.text, email, clientId);
        
        if (existingResumeByEmail) {
          console.log('✅ Found existing resume with same email (ID:', existingResumeByEmail.id, ') - FULL CACHE HIT! No OpenAI calls needed.');
          // Update IP address for analytics
          if (ipAddress && ipAddress !== 'unknown') {
            await pool.query(
              `UPDATE resumes SET ip_address = $1 WHERE id = $2 AND client_id = $3`,
              [ipAddress, existingResumeByEmail.id, clientId]
            );
          }
          // Return the existing resume - this will skip ALL OpenAI calls
          return existingResumeByEmail;
        }
      }
      
      // SECOND: Check if resume exists (by text only) - may need OpenAI for new email
      console.log('🔍 Checking if resume already exists (text only)...');
      const existingResume = await this.findExistingResume(resumeData.text, clientId);
      
      if (existingResume) {
        console.log('✅ Found existing resume (ID:', existingResume.id, ') - REUSING! No OpenAI calls needed.');
        // ALWAYS update email and IP for analytics tracking (even if null/empty)
        // This ensures we capture analytics for every upload, even for cached resumes
        const updateQuery = `
          UPDATE resumes 
          SET email = CASE 
                        WHEN $1 IS NOT NULL AND $1 != '' THEN $1 
                        ELSE email 
                      END,
              ip_address = CASE 
                            WHEN $2 IS NOT NULL AND $2 != '' AND $2 != 'unknown' THEN $2 
                            ELSE ip_address 
                          END
          WHERE id = $3 AND client_id = $4
          RETURNING *;
        `;
        const updateResult = await pool.query(updateQuery, [email, ipAddress, existingResume.id, clientId]);
        console.log('📊 Updated analytics for existing resume:', {
          email: updateResult.rows[0].email || 'NULL',
          ip_address: updateResult.rows[0].ip_address || 'NULL',
          provided_email: email || 'NULL',
          provided_ip: ipAddress || 'NULL'
        });
        return updateResult.rows[0];
      }
      
      console.log('⚡ New resume detected - creating embeddings...');
      
      // Create rich text representation for embedding
      const resumeText = embeddingService.createResumeText(resumeData);
      
      // Generate embedding (ONLY if resume doesn't exist)
      const embedding = await embeddingService.createEmbedding(resumeText);

      // Store in database
      const query = `
        INSERT INTO resumes (filename, extracted_text, parsed_data, embedding, email, ip_address, client_id)
        VALUES ($1, $2, $3, $4, $5, $6, $7)
        RETURNING *;
      `;

      const values = [
        filename,
        resumeData.text,
        JSON.stringify(resumeData.parsed),
        JSON.stringify(embedding),
        email,
        ipAddress,
        clientId,
      ];

      const result = await pool.query(query, values);
      console.log('✅ New resume stored with ID:', result.rows[0].id);
      console.log('📊 Stored analytics:', {
        email: result.rows[0].email,
        ip_address: result.rows[0].ip_address
      });
      return result.rows[0];
    } catch (error) {
      console.error('Error storing resume:', error);
      throw new Error('Failed to store resume: ' + error.message);
    }
  }

  /**
   * Calculate cosine similarity between two vectors
   * @param {Array} vecA - First vector
   * @param {Array} vecB - Second vector
   * @returns {number} Similarity score (0-1)
   */
  cosineSimilarity(vecA, vecB) {
    if (!vecA || !vecB || vecA.length !== vecB.length) return 0;
    
    let dotProduct = 0;
    let normA = 0;
    let normB = 0;
    
    for (let i = 0; i < vecA.length; i++) {
      dotProduct += vecA[i] * vecB[i];
      normA += vecA[i] * vecA[i];
      normB += vecB[i] * vecB[i];
    }
    
    const denominator = Math.sqrt(normA) * Math.sqrt(normB);
    return denominator === 0 ? 0 : dotProduct / denominator;
  }

  /**
   * Find matching jobs for a resume using semantic search (tenant-aware)
   * @param {number} resumeId - Resume ID
   * @param {number} clientId - Client ID for tenant isolation
   * @param {number} limit - Maximum number of matches to return
   * @param {number} threshold - Minimum similarity score (0-1)
   * @returns {Promise<Array>} Matching jobs with scores
   */
  async findMatchingJobs(resumeId, clientId, limit = 10, threshold = 0.5) {
    try {
      // Check if matches already exist in database (CACHE CHECK)
      console.log('🔍 Checking if matches already exist for resume', resumeId);
      const existingMatches = await this.getStoredMatches(resumeId, clientId, limit);
      
      if (existingMatches.length > 0) {
        console.log('✅ Found', existingMatches.length, 'cached matches - REUSING! No OpenAI/GPT calls needed.');
        
        // Convert stored matches to expected format and return latest 10
        const formattedMatches = existingMatches.slice(0, limit).map(match => ({
          jobId: match.job_id,
          title: match.title,
          company: match.company,
          description: match.description,
          requiredSkills: match.required_skills,
          preferredSkills: match.preferred_skills,
          experienceYears: match.experience_years,
          location: match.location,
          salaryRange: match.salary_range,
          employmentType: match.employment_type,
          semanticSimilarity: match.similarity_score,
          skillMatchScore: match.similarity_score * 100,
          combinedScore: match.similarity_score,
          directMatches: match.matched_skills || [],
          relatedMatches: [],
          missingSkills: [],
          matchReasoning: 'Cached result from previous analysis',
        }));
        
        // Ensure we return exactly the latest 10 (or limit) matches
        return formattedMatches.slice(0, limit);
      }
      
      console.log('⚡ No cached matches found - running full analysis...');
      
      // Get resume data (tenant-aware)
      const resumeQuery = `
        SELECT id, extracted_text, parsed_data, embedding, client_id
        FROM resumes
        WHERE id = $1 AND client_id = $2;
      `;
      const resumeResult = await pool.query(resumeQuery, [resumeId, clientId]);

      if (resumeResult.rows.length === 0) {
        throw new Error('Resume not found');
      }

      const resume = resumeResult.rows[0];
      const parsedData = resume.parsed_data;
      const resumeEmbedding = resume.embedding;

      // Get all job embeddings for this client (tenant-aware)
      // Jobs are stored in WordPress, only embeddings in Node.js
      const embeddingQuery = `
        SELECT 
          wp_job_id,
          embedding
        FROM job_embeddings
        WHERE client_id = $1;
      `;

      const embeddingResult = await pool.query(embeddingQuery, [clientId]);

      if (embeddingResult.rows.length === 0) {
        console.log('⚠️ No job embeddings found for this client');
        return [];
      }

      // Calculate similarity scores for all embeddings
      const embeddingsWithScores = embeddingResult.rows
        .map(row => {
          const similarity = this.cosineSimilarity(resumeEmbedding, row.embedding);
          return { 
            wp_job_id: row.wp_job_id, 
            similarity_score: similarity,
            embedding: row.embedding // Keep for AI analysis
          };
        })
        .filter(item => item.similarity_score >= threshold)
        .sort((a, b) => b.similarity_score - a.similarity_score)
        .slice(0, limit * 2); // Get more candidates for AI analysis

      // Get client's WordPress database configuration
      const dbConfig = await clientService.getClientDbConfig(clientId);
      
      if (!dbConfig) {
        throw new Error('WordPress database configuration not found. Please re-register the client.');
      }

      // Fetch actual job data from WordPress database
      const wpJobIds = embeddingsWithScores.map(item => item.wp_job_id);
      const wpJobs = await wordpressJobService.fetchJobsByIds(
        dbConfig,
        dbConfig.tablePrefix,
        wpJobIds
      );

      // Map WordPress jobs with their similarity scores
      const jobsWithScores = wpJobs.map(wpJob => {
        const embeddingData = embeddingsWithScores.find(e => e.wp_job_id === String(wpJob.id));
        return {
          ...wpJob,
          similarity_score: embeddingData.similarity_score,
          embedding: embeddingData.embedding, // For AI analysis
        };
      });

      // Enhance matches with AI-powered skill analysis
      const enhancedMatches = await Promise.all(
        jobsWithScores.map(async (job) => {
          const candidateSkills = parsedData.skills || [];
          const jobRequiredSkills = job.required_skills || [];

          // Use GPT to analyze skill compatibility
          const skillAnalysis = await embeddingService.analyzeSkillMatch(
            candidateSkills,
            jobRequiredSkills
          );

          // Calculate combined score
          // 70% semantic similarity + 30% GPT skill match score
          const combinedScore = (
            job.similarity_score * 0.7 +
            (skillAnalysis.matchScore / 100) * 0.3
          );

          return {
            jobId: job.id, // WordPress job ID
            title: job.title,
            company: job.company,
            description: job.description,
            requiredSkills: job.required_skills,
            preferredSkills: job.preferred_skills,
            experienceYears: job.experience_years,
            location: job.location,
            salaryRange: job.salary_range,
            employmentType: job.employment_type,
            semanticSimilarity: parseFloat(job.similarity_score.toFixed(3)),
            skillMatchScore: skillAnalysis.matchScore,
            combinedScore: parseFloat(combinedScore.toFixed(3)),
            directMatches: skillAnalysis.directMatches,
            relatedMatches: skillAnalysis.relatedMatches,
            missingSkills: skillAnalysis.missingSkills,
            matchReasoning: skillAnalysis.reasoning,
          };
        })
      );

      // Sort by combined score and limit to latest 10 results
      const sortedMatches = enhancedMatches
        .sort((a, b) => b.combinedScore - a.combinedScore)
        .slice(0, limit);

      // Store matches in database (tenant-aware)
      for (const match of sortedMatches) {
        await this.storeJobMatch(resumeId, clientId, match);
      }

      return sortedMatches;
    } catch (error) {
      console.error('Error finding matching jobs:', error);
      throw new Error('Failed to find matching jobs: ' + error.message);
    }
  }

  /**
   * Store job match result (tenant-aware)
   * @param {number} resumeId - Resume ID
   * @param {number} clientId - Client ID for tenant isolation
   * @param {Object} match - Match data
   */
  async storeJobMatch(resumeId, clientId, match) {
    try {
      const query = `
        INSERT INTO job_matches (resume_id, job_id, similarity_score, matched_skills, client_id)
        VALUES ($1, $2, $3, $4, $5)
        ON CONFLICT (resume_id, job_id) 
        DO UPDATE SET 
          similarity_score = EXCLUDED.similarity_score,
          matched_skills = EXCLUDED.matched_skills,
          client_id = EXCLUDED.client_id;
      `;

      const matchedSkills = [
        ...match.directMatches,
        ...match.relatedMatches.map(rm => rm.candidateSkill),
      ];

      await pool.query(query, [
        resumeId,
        match.jobId,
        match.combinedScore,
        matchedSkills,
        clientId,
      ]);
    } catch (error) {
      console.error('Error storing job match:', error);
      // Non-critical error, don't throw
    }
  }

  /**
   * Get stored matches for a resume (tenant-aware)
   * @param {number} resumeId - Resume ID
   * @param {number} clientId - Client ID for tenant isolation
   * @param {number} limit - Maximum number of matches to return
   * @returns {Promise<Array>} Previously stored matches
   */
  async getStoredMatches(resumeId, clientId, limit = 10) {
    try {
      // Get stored match records (job_id now stores wp_job_id)
      const query = `
        SELECT 
          jm.id,
          jm.similarity_score,
          jm.matched_skills,
          jm.created_at,
          jm.job_id as wp_job_id
        FROM job_matches jm
        WHERE jm.resume_id = $1 
          AND jm.client_id = $2
        ORDER BY jm.similarity_score DESC, jm.created_at DESC
        LIMIT $3;
      `;

      const result = await pool.query(query, [resumeId, clientId, limit]);
      
      if (result.rows.length === 0) {
        return [];
      }

      // Get client's WordPress database configuration
      const dbConfig = await clientService.getClientDbConfig(clientId);
      
      if (!dbConfig) {
        console.error('WordPress database configuration not found for cached matches');
        return [];
      }

      // Fetch actual job data from WordPress database
      const wpJobIds = result.rows.map(row => String(row.wp_job_id));
      const wpJobs = await wordpressJobService.fetchJobsByIds(
        dbConfig,
        dbConfig.tablePrefix,
        wpJobIds
      );

      // Map WordPress jobs with stored match data
      return result.rows.map(row => {
        const wpJob = wpJobs.find(job => String(job.id) === String(row.wp_job_id));
        if (!wpJob) {
          return null; // Job might have been deleted from WordPress
        }
        return {
          job_id: wpJob.id,
          title: wpJob.title,
          company: wpJob.company,
          description: wpJob.description,
          required_skills: wpJob.required_skills,
          preferred_skills: wpJob.preferred_skills,
          experience_years: wpJob.experience_years,
          location: wpJob.location,
          salary_range: wpJob.salary_range,
          employment_type: wpJob.employment_type,
          similarity_score: row.similarity_score,
          matched_skills: row.matched_skills,
          created_at: row.created_at,
        };
      }).filter(match => match !== null); // Remove null entries
    } catch (error) {
      console.error('Error getting stored matches:', error);
      return [];
    }
  }
}

export default new JobMatchingService();

