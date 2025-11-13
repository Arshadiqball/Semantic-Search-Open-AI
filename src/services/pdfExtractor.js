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
    techSkills.forEach(skill => {
      const regex = new RegExp(`\\b${skill}\\b`, 'gi');
      if (regex.test(text)) {
        const normalizedSkill = skill.replace(/\\([+()])/g, '$1');
        if (!parsed.skills.includes(normalizedSkill)) {
          parsed.skills.push(normalizedSkill);
        }
      }
    });

    // Extract years of experience using both explicit mentions and inferred date ranges
    const now = new Date();
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthMap = {
      jan: 0, january: 0,
      feb: 1, february: 1,
      mar: 2, march: 2,
      apr: 3, april: 3,
      may: 4,
      jun: 5, june: 5,
      jul: 6, july: 6,
      aug: 7, august: 7,
      sep: 8, sept: 8, september: 8,
      oct: 9, october: 9,
      nov: 10, november: 10,
      dec: 11, december: 11,
    };

    const normalizeSeparators = (value) => value.replace(/[\u2012\u2013\u2014\u2015]/g, '-');
    const cleanText = normalizeSeparators(text);

    const diffInMonths = (startDate, endDate) => {
      const start = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
      const end = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
      return (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth()) + 1;
    };

    const parseDateComponent = (component, { isEnd = false } = {}) => {
      if (!component) return null;

      const cleanedRaw = normalizeSeparators(component).replace(/\./g, '').trim();
      if (!cleanedRaw) return null;

      if (/present|current|now/i.test(cleanedRaw)) {
        const presentDate = new Date(now.getFullYear(), now.getMonth(), 1);
        return {
          label: 'Present',
          date: presentDate,
          year: presentDate.getFullYear(),
          month: presentDate.getMonth(),
          isPresent: true,
        };
      }

      const mmYYYY = cleanedRaw.match(/^(\d{1,2})[\/\-](\d{2,4})$/);
      if (mmYYYY) {
        const month = Math.max(0, Math.min(11, parseInt(mmYYYY[1], 10) - 1));
        let year = parseInt(mmYYYY[2], 10);
        if (String(year).length === 2) {
          year += year >= 70 ? 1900 : 2000;
        }
        if (Number.isNaN(year) || year < 1950 || year > now.getFullYear() + 5) {
          return null;
        }
        return {
          label: `${monthNames[month]} ${year}`,
          date: new Date(year, month, 1),
          year,
          month,
        };
      }

      const tokens = cleanedRaw.toLowerCase().split(/\s+/).filter(Boolean);
      let month = null;
      let year = null;

      if (tokens.length === 1) {
        if (/^\d{4}$/.test(tokens[0])) {
          year = parseInt(tokens[0], 10);
          month = isEnd ? 11 : 0;
        } else if (monthMap[tokens[0]] !== undefined) {
          month = monthMap[tokens[0]];
        }
      } else if (tokens.length >= 2) {
        const monthToken = tokens[0];
        if (monthMap[monthToken] !== undefined) {
          month = monthMap[monthToken];
          const yearToken = tokens[tokens.length - 1];
          if (/^\d{4}$/.test(yearToken)) {
            year = parseInt(yearToken, 10);
          }
        }
      }

      if (year === null) {
        const yearMatch = cleanedRaw.match(/(\d{4})/);
        if (yearMatch) {
          year = parseInt(yearMatch[1], 10);
          if (month === null) {
            month = isEnd ? 11 : 0;
          }
        }
      }

      if (year === null || Number.isNaN(year) || year < 1950 || year > now.getFullYear() + 5) {
        return null;
      }

      if (month === null) {
        month = isEnd ? 11 : 0;
      }

      const safeMonth = Math.max(0, Math.min(11, month));
      return {
        label: `${monthNames[safeMonth]} ${year}`,
        date: new Date(year, safeMonth, 1),
        year,
        month: safeMonth,
      };
    };

    const computeExperienceFromDateRanges = () => {
      const rangeRegex = /((?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?|\d{1,2}[\/\-])\s*\d{2,4}|\d{4})\s*(?:-|–|—|to|through|until)\s*((?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?|\d{1,2}[\/\-])\s*(?:\d{2,4}|present|current|now)|\d{4}|present|current|now)/gi;
      const ranges = [];

      for (const match of cleanText.matchAll(rangeRegex)) {
        const startRaw = match[1]?.trim();
        const endRaw = match[2]?.trim();

        const startInfo = parseDateComponent(startRaw, { isEnd: false });
        const endInfo = parseDateComponent(endRaw, { isEnd: true });

        if (!startInfo || !endInfo) continue;
        if (endInfo.date < startInfo.date) continue;

        const months = diffInMonths(startInfo.date, endInfo.date);
        if (months <= 0 || months / 12 > 50) continue;

        ranges.push({
          startDate: new Date(startInfo.date.getFullYear(), startInfo.date.getMonth(), 1),
          endDate: new Date(endInfo.date.getFullYear(), endInfo.date.getMonth(), 1),
          startLabel: startInfo.label,
          endLabel: endInfo.label,
          sourceTexts: [match[0]],
        });
      }

      if (ranges.length === 0) {
        return { years: 0, details: [] };
      }

      ranges.sort((a, b) => a.startDate - b.startDate);

      const merged = [];
      let current = null;

      for (const range of ranges) {
        if (!current) {
          current = { ...range };
          continue;
        }

        if (range.startDate <= current.endDate) {
          if (range.endDate > current.endDate) {
            current.endDate = new Date(range.endDate.getFullYear(), range.endDate.getMonth(), 1);
            current.endLabel = range.endLabel;
          }
          current.sourceTexts.push(...range.sourceTexts);
        } else {
          merged.push(current);
          current = { ...range };
        }
      }

      if (current) {
        merged.push(current);
      }

      let totalMonths = 0;
      const details = merged.map((range) => {
        const months = diffInMonths(range.startDate, range.endDate);
        totalMonths += months;
        return {
          type: 'dateRange',
          start: range.startLabel,
          end: range.endLabel,
          durationMonths: months,
          durationYears: Number((months / 12).toFixed(1)),
          sources: range.sourceTexts,
        };
      });

      return {
        years: totalMonths / 12,
        details,
      };
    };

    const computeExperienceFromMentions = () => {
      const mentionDetails = [];
      const seen = new Set();
      let maxValue = 0;

      const patterns = [
        {
          regex: /(\d+(?:\.\d+)?)\+?\s*(?:years?|yrs?)(?:\s+of)?\s+(?:experience|exp\b)/gi,
          extract: (match) => parseFloat(match[1]),
        },
        {
          regex: /(?:experience|exp\b)[^\d]{0,20}(\d+(?:\.\d+)?)(?:\s*\+)?\s*(?:years?|yrs?)/gi,
          extract: (match) => parseFloat(match[1]),
        },
        {
          regex: /(\d+)\s+years?\s+(?:and\s+)?(\d+)\s+months?/gi,
          extract: (match) => {
            const years = parseInt(match[1], 10);
            const months = parseInt(match[2], 10);
            if (Number.isNaN(years) || Number.isNaN(months)) return NaN;
            return years + months / 12;
          },
        },
      ];

      const generalRegex = /(\d+(?:\.\d+)?)\s*(?:\+)?\s*(?:years?|yrs?)/gi;

      const addMention = (match, value) => {
        const index = match.index ?? text.indexOf(match[0]);
        if (index === -1) return;

        const key = `${index}-${match[0]}`;
        if (seen.has(key)) return;
        seen.add(key);

        if (!Number.isFinite(value) || value <= 0) return;

        const snippet = text.slice(Math.max(0, index - 40), Math.min(text.length, index + match[0].length + 40));

        mentionDetails.push({
          type: 'mention',
          valueYears: Number(value.toFixed(2)),
          snippet: snippet.trim(),
        });

        if (value > maxValue) {
          maxValue = value;
        }
      };

      patterns.forEach(({ regex, extract }) => {
        for (const match of text.matchAll(regex)) {
          const value = extract(match);
          addMention(match, value);
        }
      });

      for (const match of text.matchAll(generalRegex)) {
        const value = parseFloat(match[1]);
        const index = match.index ?? text.indexOf(match[0]);
        if (index === -1) continue;

        const snippet = text.slice(Math.max(0, index - 40), Math.min(text.length, index + match[0].length + 40));
        if (!/(experience|exp|work|working|professional|industry|career|employment|software|engineering|management)/i.test(snippet)) {
          continue;
        }

        addMention(match, value);
      }

      return {
        years: maxValue,
        details: mentionDetails,
      };
    };

    const rangeExperienceInfo = computeExperienceFromDateRanges();
    const mentionExperienceInfo = computeExperienceFromMentions();

    let totalExperience = mentionExperienceInfo.years;

    if (rangeExperienceInfo.years > totalExperience) {
      totalExperience = rangeExperienceInfo.years;
    }

    if (rangeExperienceInfo.details.length > 0) {
      parsed.experience = rangeExperienceInfo.details;
    } else if (mentionExperienceInfo.details.length > 0) {
      parsed.experience = mentionExperienceInfo.details;
    }

    parsed.experienceYears = totalExperience > 0 ? Number(totalExperience.toFixed(1)) : 0;

    if (parsed.experienceYears > 0) {
      parsed.experienceSource = rangeExperienceInfo.years > mentionExperienceInfo.years ? 'dateRanges' : 'explicitMentions';
    }

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

