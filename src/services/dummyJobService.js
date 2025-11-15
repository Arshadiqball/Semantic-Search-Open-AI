import pool from '../config/database.js';
import embeddingService from './embeddingService.js';

/**
 * Service to generate dummy jobs for testing
 */
class DummyJobService {
  /**
   * Generate dummy jobs for a client
   * @param {number} clientId - Client ID
   * @param {number} count - Number of jobs to generate (default: 100)
   * @returns {Promise<Object>} Result with count of created jobs
   */
  async generateDummyJobs(clientId, count = 200) {
    try {
      console.log(`[Client ID: ${clientId}] Generating ${count} dummy jobs...`);

      const jobTemplates = this.getJobTemplates();
      const createdJobs = [];

      for (let i = 0; i < count; i++) {
        const template = jobTemplates[i % jobTemplates.length];
        const job = this.createJobFromTemplate(template, i);

        // Create text representation for embedding
        const jobText = embeddingService.createJobText(job);
        
        // Generate embedding
        const embedding = await embeddingService.createEmbedding(jobText);

        // Insert job with client_id
        const query = `
          INSERT INTO jobs (
            title, company, description, required_skills, preferred_skills,
            experience_years, location, salary_range, employment_type, embedding, client_id
          )
          VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
          RETURNING id, title, company;
        `;

        const values = [
          job.title,
          job.company,
          job.description,
          job.required_skills,
          job.preferred_skills || null,
          job.experience_years,
          job.location,
          job.salary_range,
          job.employment_type,
          JSON.stringify(embedding),
          clientId,
        ];

        const result = await pool.query(query, values);
        createdJobs.push(result.rows[0]);

        // Small delay to avoid rate limiting
        if ((i + 1) % 10 === 0) {
          console.log(`  → Created ${i + 1}/${count} jobs...`);
          await new Promise(resolve => setTimeout(resolve, 500));
        }
      }

      console.log(`✅ Successfully created ${createdJobs.length} dummy jobs for client ${clientId}`);
      return {
        success: true,
        count: createdJobs.length,
        jobs: createdJobs,
      };
    } catch (error) {
      console.error('Error generating dummy jobs:', error);
      throw new Error('Failed to generate dummy jobs: ' + error.message);
    }
  }

  /**
   * Get job templates for generating dummy jobs
   */
  getJobTemplates() {
    return [
      {
        title: 'Senior Full Stack Developer',
        company: 'TechCorp',
        description: 'We are looking for an experienced Full Stack Developer to join our team. You will be responsible for developing and maintaining web applications using modern technologies.',
        required_skills: ['JavaScript', 'React', 'Node.js', 'PostgreSQL', 'REST APIs'],
        preferred_skills: ['TypeScript', 'AWS', 'Docker', 'GraphQL'],
        experience_years: 5,
        location: 'San Francisco, CA',
        salary_range: '$120,000 - $180,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Frontend Developer',
        company: 'WebSolutions Inc',
        description: 'Join our frontend team to build beautiful and responsive user interfaces. Work with cutting-edge technologies and collaborate with designers and backend developers.',
        required_skills: ['React', 'TypeScript', 'CSS', 'HTML', 'JavaScript'],
        preferred_skills: ['Vue.js', 'Next.js', 'Tailwind CSS', 'Redux'],
        experience_years: 3,
        location: 'New York, NY',
        salary_range: '$90,000 - $130,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Backend Developer',
        company: 'CloudTech',
        description: 'We need a skilled Backend Developer to design and implement scalable server-side applications. Experience with microservices architecture is a plus.',
        required_skills: ['Python', 'Django', 'PostgreSQL', 'REST APIs', 'Linux'],
        preferred_skills: ['FastAPI', 'Redis', 'Kubernetes', 'Docker', 'AWS'],
        experience_years: 4,
        location: 'Austin, TX',
        salary_range: '$100,000 - $150,000',
        employment_type: 'Full-time',
      },
      {
        title: 'DevOps Engineer',
        company: 'Infrastructure Pro',
        description: 'Looking for a DevOps Engineer to manage our cloud infrastructure and CI/CD pipelines. Help us scale our systems efficiently.',
        required_skills: ['AWS', 'Docker', 'Kubernetes', 'CI/CD', 'Linux'],
        preferred_skills: ['Terraform', 'Ansible', 'Jenkins', 'GitLab CI', 'Monitoring'],
        experience_years: 4,
        location: 'Seattle, WA',
        salary_range: '$110,000 - $160,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Data Scientist',
        company: 'DataAnalytics Co',
        description: 'Join our data science team to build machine learning models and analyze large datasets. Work on exciting projects that drive business decisions.',
        required_skills: ['Python', 'Machine Learning', 'SQL', 'Pandas', 'NumPy'],
        preferred_skills: ['TensorFlow', 'PyTorch', 'Spark', 'Jupyter', 'Statistics'],
        experience_years: 3,
        location: 'Boston, MA',
        salary_range: '$95,000 - $140,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Mobile App Developer',
        company: 'MobileFirst',
        description: 'We are seeking a Mobile App Developer to create native and cross-platform mobile applications. Work on iOS and Android platforms.',
        required_skills: ['React Native', 'JavaScript', 'iOS', 'Android', 'REST APIs'],
        preferred_skills: ['Swift', 'Kotlin', 'Flutter', 'Firebase', 'App Store'],
        experience_years: 3,
        location: 'Los Angeles, CA',
        salary_range: '$85,000 - $125,000',
        employment_type: 'Full-time',
      },
      {
        title: 'UI/UX Designer',
        company: 'DesignStudio',
        description: 'Looking for a creative UI/UX Designer to design user-friendly interfaces and improve user experience across our products.',
        required_skills: ['Figma', 'Adobe XD', 'User Research', 'Wireframing', 'Prototyping'],
        preferred_skills: ['Sketch', 'InVision', 'HTML/CSS', 'Design Systems', 'Accessibility'],
        experience_years: 2,
        location: 'Portland, OR',
        salary_range: '$70,000 - $100,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Product Manager',
        company: 'ProductLab',
        description: 'We need an experienced Product Manager to lead product development, work with cross-functional teams, and drive product strategy.',
        required_skills: ['Product Strategy', 'Agile', 'Stakeholder Management', 'Analytics', 'Roadmapping'],
        preferred_skills: ['SQL', 'A/B Testing', 'User Research', 'Technical Background', 'Scrum'],
        experience_years: 5,
        location: 'Chicago, IL',
        salary_range: '$100,000 - $150,000',
        employment_type: 'Full-time',
      },
      {
        title: 'QA Engineer',
        company: 'QualityAssurance Ltd',
        description: 'Join our QA team to ensure software quality through comprehensive testing. Write test cases and automate testing processes.',
        required_skills: ['Testing', 'Selenium', 'Python', 'Test Automation', 'Bug Tracking'],
        preferred_skills: ['Cypress', 'Jest', 'API Testing', 'Performance Testing', 'CI/CD'],
        experience_years: 2,
        location: 'Denver, CO',
        salary_range: '$65,000 - $95,000',
        employment_type: 'Full-time',
      },
      {
        title: 'Security Engineer',
        company: 'SecureTech',
        description: 'We are looking for a Security Engineer to protect our systems and applications from security threats. Conduct security audits and implement security measures.',
        required_skills: ['Security', 'Penetration Testing', 'Linux', 'Network Security', 'OWASP'],
        preferred_skills: ['AWS Security', 'Kubernetes Security', 'SIEM', 'Compliance', 'Incident Response'],
        experience_years: 4,
        location: 'Washington, DC',
        salary_range: '$110,000 - $160,000',
        employment_type: 'Full-time',
      },
    ];
  }

  /**
   * Create a job from template with variations
   */
  createJobFromTemplate(template, index) {
    const variations = [
      'Senior', 'Lead', 'Principal', 'Mid-level', 'Junior', 'Associate',
    ];
    const companies = [
      'TechCorp', 'WebSolutions Inc', 'CloudTech', 'Infrastructure Pro',
      'DataAnalytics Co', 'MobileFirst', 'DesignStudio', 'ProductLab',
      'QualityAssurance Ltd', 'SecureTech', 'InnovationHub', 'StartupXYZ',
    ];
    const locations = [
      'San Francisco, CA', 'New York, NY', 'Austin, TX', 'Seattle, WA',
      'Boston, MA', 'Los Angeles, CA', 'Portland, OR', 'Chicago, IL',
      'Denver, CO', 'Washington, DC', 'Remote', 'Hybrid',
    ];
    const salaryRanges = [
      '$80,000 - $120,000', '$90,000 - $130,000', '$100,000 - $150,000',
      '$110,000 - $160,000', '$120,000 - $180,000', '$130,000 - $200,000',
    ];

    const variation = variations[index % variations.length];
    const company = companies[index % companies.length];
    const location = locations[index % locations.length];
    const salary = salaryRanges[index % salaryRanges.length];

    // Add variation to title if not already present
    let title = template.title;
    if (!title.includes(variation.split(' ')[0])) {
      title = `${variation} ${template.title}`;
    }

    return {
      ...template,
      title,
      company,
      location,
      salary_range: salary,
      experience_years: template.experience_years + (index % 3) - 1, // Vary experience
    };
  }
}

export default new DummyJobService();

