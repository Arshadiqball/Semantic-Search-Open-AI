import OpenAI from 'openai';
import dotenv from 'dotenv';

dotenv.config();

class EmbeddingService {
  constructor() {
    this.openai = new OpenAI({
      apiKey: process.env.OPENAI_API_KEY,
    });
    this.model = 'text-embedding-3-small'; // More cost-effective, 1536 dimensions
  }

  /**
   * Create embedding vector for text
   * @param {string} text - Text to embed
   * @returns {Promise<Array<number>>} Embedding vector
   */
  async createEmbedding(text) {
    try {
      const response = await this.openai.embeddings.create({
        model: this.model,
        input: text,
      });

      return response.data[0].embedding;
    } catch (error) {
      console.error('Error creating embedding:', error);
      throw new Error('Failed to create embedding: ' + error.message);
    }
  }

  /**
   * Create embeddings for multiple texts
   * @param {Array<string>} texts - Array of texts to embed
   * @returns {Promise<Array<Array<number>>>} Array of embedding vectors
   */
  async createBatchEmbeddings(texts) {
    try {
      const response = await this.openai.embeddings.create({
        model: this.model,
        input: texts,
      });

      return response.data.map(item => item.embedding);
    } catch (error) {
      console.error('Error creating batch embeddings:', error);
      throw new Error('Failed to create batch embeddings: ' + error.message);
    }
  }

  /**
   * Create a rich text representation for job posting
   * @param {Object} job - Job object
   * @returns {string} Text representation
   */
  createJobText(job) {
    const parts = [
      `Job Title: ${job.title}`,
      `Company: ${job.company}`,
      `Description: ${job.description}`,
      `Required Skills: ${job.required_skills.join(', ')}`,
    ];

    if (job.preferred_skills && job.preferred_skills.length > 0) {
      parts.push(`Preferred Skills: ${job.preferred_skills.join(', ')}`);
    }

    if (job.experience_years) {
      parts.push(`Experience Required: ${job.experience_years} years`);
    }

    if (job.location) {
      parts.push(`Location: ${job.location}`);
    }

    if (job.employment_type) {
      parts.push(`Employment Type: ${job.employment_type}`);
    }

    return parts.join('\n');
  }

  /**
   * Create a rich text representation for resume
   * @param {Object} resume - Resume object with text and parsed data
   * @returns {string} Text representation
   */
  createResumeText(resume) {
    const parts = [];

    // Add the full extracted text
    if (resume.text) {
      parts.push(resume.text);
    }

    // Enhance with parsed data
    if (resume.parsed) {
      if (resume.parsed.skills && resume.parsed.skills.length > 0) {
        parts.push(`\nKey Technical Skills: ${resume.parsed.skills.join(', ')}`);
      }

      if (resume.parsed.experienceYears) {
        parts.push(`Total Experience: ${resume.parsed.experienceYears} years`);
      }

      if (resume.parsed.education && resume.parsed.education.length > 0) {
        parts.push(`Education: ${resume.parsed.education.join(', ')}`);
      }
    }

    return parts.join('\n');
  }

  /**
   * Use ChatGPT to enhance skill matching and find related technologies
   * @param {Array<string>} candidateSkills - Skills from resume
   * @param {Array<string>} jobRequiredSkills - Required skills for job
   * @returns {Promise<Object>} Analysis result
   */
  async analyzeSkillMatch(candidateSkills, jobRequiredSkills) {
    try {
      const prompt = `Analyze skill match between candidate and job.

Candidate: ${candidateSkills.join(', ')}
Required: ${jobRequiredSkills.join(', ')}

Return JSON only:
{
  "directMatches": ["exact matches"],
  "relatedMatches": [{"candidateSkill": "X", "jobSkill": "Y", "reasoning": "brief"}],
  "missingSkills": ["missing"],
  "matchScore": 0-100,
  "reasoning": "1 sentence"
}`;

      const response = await this.openai.chat.completions.create({
        model: 'gpt-4o-mini', // Cheapest model - 15x cheaper than gpt-3.5-turbo!
        messages: [{ role: 'user', content: prompt }],
        temperature: 0.3,
        max_tokens: 200, // Reduced from 500 - JSON response is small
      });

      const content = response.choices[0].message.content.trim();
      
      // Extract JSON from markdown code blocks if present
      let jsonContent = content;
      const jsonMatch = content.match(/```(?:json)?\s*\n?([\s\S]*?)\n?```/);
      if (jsonMatch) {
        jsonContent = jsonMatch[1];
      }
      
      return JSON.parse(jsonContent);
    } catch (error) {
      console.error('Error analyzing skill match:', error);
      // Return a basic analysis if GPT fails
      const directMatches = candidateSkills.filter(skill => 
        jobRequiredSkills.some(reqSkill => 
          reqSkill.toLowerCase() === skill.toLowerCase()
        )
      );

      return {
        directMatches,
        relatedMatches: [],
        missingSkills: jobRequiredSkills.filter(skill => 
          !directMatches.includes(skill)
        ),
        matchScore: (directMatches.length / jobRequiredSkills.length) * 100,
        reasoning: 'Basic skill matching applied',
      };
    }
  }
}

export default new EmbeddingService();

