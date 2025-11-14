import pool from '../config/database.js';
import embeddingService from './embeddingService.js';

class JobMatchingService {
  /**
   * Check if similar resume already exists
   * @param {string} extractedText - Resume text
   * @returns {Promise<Object|null>} Existing resume or null
   */
  async findExistingResume(extractedText) {
    try {
      // Create a simple hash of the text for comparison
      const textHash = extractedText.substring(0, 100).trim();
      
      const query = `
        SELECT id, filename, extracted_text, parsed_data, embedding, email, created_at
        FROM resumes
        WHERE LEFT(extracted_text, 100) = $1
        ORDER BY created_at DESC
        LIMIT 1;
      `;
      
      const result = await pool.query(query, [textHash]);
      
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
   * Check if resume with same text AND email already exists
   * @param {string} extractedText - Resume text
   * @param {string} email - User email
   * @returns {Promise<Object|null>} Existing resume or null
   */
  async findExistingResumeByEmail(extractedText, email) {
    try {
      if (!email) return null; // Can't match by email if no email provided
      
      const textHash = extractedText.substring(0, 100).trim();
      
      const query = `
        SELECT id, filename, extracted_text, parsed_data, embedding, email, ip_address, created_at
        FROM resumes
        WHERE LEFT(extracted_text, 100) = $1
          AND email = $2
        ORDER BY created_at DESC
        LIMIT 1;
      `;
      
      const result = await pool.query(query, [textHash, email]);
      
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
   * Store resume in database with embedding
   * @param {Object} resumeData - Resume data from PDF extractor
   * @param {string} filename - Original filename
   * @param {string} email - User email address
   * @param {string} ipAddress - User IP address
   * @returns {Promise<Object>} Stored resume record
   */
  async storeResume(resumeData, filename, email = null, ipAddress = null) {
    try {
      // FIRST: Check if same resume + same email exists (STRICT CACHE - NO OpenAI calls)
      if (email) {
        console.log('🔍 Checking if resume with same email already exists...');
        const existingResumeByEmail = await this.findExistingResumeByEmail(resumeData.text, email);
        
        if (existingResumeByEmail) {
          console.log('✅ Found existing resume with same email (ID:', existingResumeByEmail.id, ') - FULL CACHE HIT! No OpenAI calls needed.');
          // Update IP address for analytics
          if (ipAddress && ipAddress !== 'unknown') {
            await pool.query(
              `UPDATE resumes SET ip_address = $1 WHERE id = $2`,
              [ipAddress, existingResumeByEmail.id]
            );
          }
          // Return the existing resume - this will skip ALL OpenAI calls
          return existingResumeByEmail;
        }
      }
      
      // SECOND: Check if resume exists (by text only) - may need OpenAI for new email
      console.log('🔍 Checking if resume already exists (text only)...');
      const existingResume = await this.findExistingResume(resumeData.text);
      
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
          WHERE id = $3
          RETURNING *;
        `;
        const updateResult = await pool.query(updateQuery, [email, ipAddress, existingResume.id]);
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
        INSERT INTO resumes (filename, extracted_text, parsed_data, embedding, email, ip_address)
        VALUES ($1, $2, $3, $4, $5, $6)
        RETURNING *;
      `;

      const values = [
        filename,
        resumeData.text,
        JSON.stringify(resumeData.parsed),
        JSON.stringify(embedding),
        email,
        ipAddress,
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
   * Find matching jobs for a resume using semantic search
   * @param {number} resumeId - Resume ID
   * @param {number} limit - Maximum number of matches to return
   * @param {number} threshold - Minimum similarity score (0-1)
   * @returns {Promise<Array>} Matching jobs with scores
   */
  async findMatchingJobs(resumeId, limit = 10, threshold = 0.5) {
    try {
      // Check if matches already exist in database (CACHE CHECK)
      console.log('🔍 Checking if matches already exist for resume', resumeId);
      const existingMatches = await this.getStoredMatches(resumeId, limit);
      
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
      
      // Get resume data
      const resumeQuery = `
        SELECT id, extracted_text, parsed_data, embedding
        FROM resumes
        WHERE id = $1;
      `;
      const resumeResult = await pool.query(resumeQuery, [resumeId]);

      if (resumeResult.rows.length === 0) {
        throw new Error('Resume not found');
      }

      const resume = resumeResult.rows[0];
      const parsedData = resume.parsed_data;
      const resumeEmbedding = resume.embedding;

      // Get all jobs (we'll calculate similarity in JavaScript)
      const jobQuery = `
        SELECT 
          id,
          title,
          company,
          description,
          required_skills,
          preferred_skills,
          experience_years,
          location,
          salary_range,
          employment_type,
          embedding
        FROM jobs;
      `;

      const jobResult = await pool.query(jobQuery);

      // Calculate similarity scores for all jobs
      const jobsWithScores = jobResult.rows
        .map(job => {
          const similarity = this.cosineSimilarity(resumeEmbedding, job.embedding);
          return { ...job, similarity_score: similarity };
        })
        .filter(job => job.similarity_score >= threshold)
        .sort((a, b) => b.similarity_score - a.similarity_score)
        .slice(0, limit * 2); // Get more candidates for AI analysis

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
            jobId: job.id,
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

      // Store matches in database
      for (const match of sortedMatches) {
        await this.storeJobMatch(resumeId, match);
      }

      return sortedMatches;
    } catch (error) {
      console.error('Error finding matching jobs:', error);
      throw new Error('Failed to find matching jobs: ' + error.message);
    }
  }

  /**
   * Store job match result
   * @param {number} resumeId - Resume ID
   * @param {Object} match - Match data
   */
  async storeJobMatch(resumeId, match) {
    try {
      const query = `
        INSERT INTO job_matches (resume_id, job_id, similarity_score, matched_skills)
        VALUES ($1, $2, $3, $4)
        ON CONFLICT (resume_id, job_id) 
        DO UPDATE SET 
          similarity_score = EXCLUDED.similarity_score,
          matched_skills = EXCLUDED.matched_skills;
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
      ]);
    } catch (error) {
      console.error('Error storing job match:', error);
      // Non-critical error, don't throw
    }
  }

  /**
   * Get stored matches for a resume
   * @param {number} resumeId - Resume ID
   * @returns {Promise<Array>} Previously stored matches
   */
  async getStoredMatches(resumeId, limit = 10) {
    try {
      const query = `
        SELECT 
          jm.id,
          jm.similarity_score,
          jm.matched_skills,
          jm.created_at,
          j.id as job_id,
          j.title,
          j.company,
          j.description,
          j.required_skills,
          j.preferred_skills,
          j.experience_years,
          j.location,
          j.salary_range,
          j.employment_type
        FROM job_matches jm
        JOIN jobs j ON jm.job_id = j.id
        WHERE jm.resume_id = $1
        ORDER BY jm.similarity_score DESC, jm.created_at DESC
        LIMIT $2;
      `;

      const result = await pool.query(query, [resumeId, limit]);
      return result.rows;
    } catch (error) {
      console.error('Error getting stored matches:', error);
      throw new Error('Failed to get stored matches: ' + error.message);
    }
  }
}

export default new JobMatchingService();

