import fs from 'fs/promises';
import pdfParse from 'pdf-parse';

class PDFExtractor {
  /**
   * Extract text content from PDF file
   * @param {string} filePath - Path to the PDF file
   * @returns {Promise<Object>} Extracted data
   */
  async extractText(filePath) {
    try {
      const dataBuffer = await fs.readFile(filePath);
      const pdfData = await pdfParse(dataBuffer);

      return {
        text: pdfData.text,
        pages: pdfData.numpages,
        info: pdfData.info,
      };
    } catch (error) {
      console.error('Error extracting PDF:', error);
      throw new Error('Failed to extract PDF content: ' + error.message);
    }
  }

  /**
   * Parse resume text to extract structured information
   * @param {string} text - Raw text from resume
   * @returns {Object} Parsed resume data
   */
  parseResumeText(text) {
    const parsed = {
      skills: [],
      experience: [],
      education: [],
      languages: [],
      frameworks: [],
      tools: [],
    };

    // Common tech skills patterns
    const techSkills = [
      // Programming Languages
      'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'C\\+\\+', 'PHP', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin',
      'Scala', 'R', 'MATLAB', 'Perl', 'Shell', 'Bash', 'PowerShell',
      
      // Web Technologies
      'HTML', 'CSS', 'SASS', 'LESS', 'React', 'Vue', 'Angular', 'Svelte', 'jQuery', 'Node.js', 'Express',
      'Next.js', 'Nuxt', 'Gatsby', 'Redux', 'MobX', 'Webpack', 'Vite', 'Babel',
      
      // Backend Frameworks
      'Django', 'Flask', 'FastAPI', 'Spring', 'Spring Boot', 'Laravel', 'Symfony', 'Rails', 'ASP.NET', '.NET Core',
      'NestJS', 'Koa', 'Fastify',
      
      // Databases
      'PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'Cassandra', 'DynamoDB', 'Elasticsearch', 'SQLite', 'Oracle',
      'SQL Server', 'MariaDB', 'Neo4j', 'CouchDB',
      
      // Cloud & DevOps
      'AWS', 'Azure', 'GCP', 'Google Cloud', 'Docker', 'Kubernetes', 'Jenkins', 'GitLab CI', 'GitHub Actions',
      'CircleCI', 'Travis CI', 'Terraform', 'Ansible', 'Puppet', 'Chef',
      
      // Mobile
      'React Native', 'Flutter', 'Ionic', 'Xamarin', 'Android', 'iOS',
      
      // Data & AI
      'TensorFlow', 'PyTorch', 'Keras', 'scikit-learn', 'Pandas', 'NumPy', 'Apache Spark', 'Hadoop', 'Kafka',
      'Machine Learning', 'Deep Learning', 'NLP', 'Computer Vision', 'Data Science',
      
      // Testing
      'Jest', 'Mocha', 'Chai', 'Cypress', 'Selenium', 'Playwright', 'JUnit', 'PyTest', 'PHPUnit',
      
      // Others
      'GraphQL', 'REST', 'gRPC', 'WebSocket', 'OAuth', 'JWT', 'Git', 'GitHub', 'GitLab', 'Bitbucket',
      'Jira', 'Agile', 'Scrum', 'Microservices', 'API', 'CI/CD', 'Linux', 'Unix', 'Windows Server',
    ];

    // Extract skills
    const textUpper = text.toUpperCase();
    const textOriginal = text;
    
    techSkills.forEach(skill => {
      const regex = new RegExp(`\\b${skill}\\b`, 'gi');
      if (regex.test(text)) {
        const normalizedSkill = skill.replace(/\\([+()])/g, '$1');
        if (!parsed.skills.includes(normalizedSkill)) {
          parsed.skills.push(normalizedSkill);
        }
      }
    });

    // Extract years of experience
    const expPatterns = [
      /(\d+)\+?\s*(?:years?|yrs?)(?:\s+of)?\s+experience/gi,
      /experience[:\s]+(\d+)\+?\s*(?:years?|yrs?)/gi,
    ];

    let totalExperience = 0;
    expPatterns.forEach(pattern => {
      const matches = text.matchAll(pattern);
      for (const match of matches) {
        const years = parseInt(match[1]);
        if (years > totalExperience) {
          totalExperience = years;
        }
      }
    });

    parsed.experienceYears = totalExperience;

    // Extract education
    const educationKeywords = ['Bachelor', 'Master', 'PhD', 'B.S.', 'M.S.', 'B.Tech', 'M.Tech', 'MBA', 'Diploma'];
    educationKeywords.forEach(keyword => {
      if (text.includes(keyword)) {
        parsed.education.push(keyword);
      }
    });

    return parsed;
  }

  /**
   * Process resume file and extract all information
   * @param {string} filePath - Path to the PDF file
   * @returns {Promise<Object>} Complete resume data
   */
  async processResume(filePath) {
    const extracted = await this.extractText(filePath);
    const parsed = this.parseResumeText(extracted.text);

    return {
      text: extracted.text,
      pages: extracted.pages,
      parsed: parsed,
    };
  }
}

export default new PDFExtractor();

