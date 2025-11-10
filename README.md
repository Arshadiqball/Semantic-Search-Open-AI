# Semantic Job Matcher

An intelligent job matching system that uses OpenAI embeddings and semantic search to match candidate resumes with relevant job openings. The system goes beyond simple keyword matching by understanding related skills and technologies.

## 🌟 Features

- **PDF Resume Parsing**: Extract text and structured information from PDF resumes
- **Semantic Search**: Uses OpenAI embeddings to find similar jobs based on meaning, not just keywords
- **Intelligent Skill Matching**: AI-powered analysis that understands related technologies (e.g., Laravel experience implies PHP knowledge)
- **Vector Similarity**: PostgreSQL with pgvector for efficient similarity searches
- **RESTful API**: Clean API endpoints for resume upload and job matching

## 🎯 How It Works

1. **Upload Resume**: User uploads a PDF resume
2. **Extract Information**: System extracts text and parses skills, experience, education
3. **Generate Embeddings**: Creates vector embeddings using OpenAI's embedding model
4. **Semantic Search**: Finds similar jobs using cosine similarity in vector space
5. **AI Enhancement**: Uses GPT to analyze skill compatibility and related technologies
6. **Return Matches**: Returns ranked list of matching jobs with detailed analysis

### Example Intelligence

If a candidate's resume mentions:
- **Laravel** → System understands they know **PHP**
- **Express.js** → System knows they can work with **Node.js**
- **React** → System infers **JavaScript** expertise

## 🚀 Setup Instructions

### Prerequisites

- Node.js 18+ 
- PostgreSQL 14+ with pgvector extension
- OpenAI API key

### 1. Install PostgreSQL and pgvector

**On macOS (using Homebrew):**
```bash
brew install postgresql@14
brew services start postgresql@14

# Install pgvector
git clone https://github.com/pgvector/pgvector.git
cd pgvector
make
make install
```

**On Ubuntu/Debian:**
```bash
sudo apt-get install postgresql postgresql-contrib
sudo systemctl start postgresql

# Install pgvector
sudo apt-get install postgresql-server-dev-14
git clone https://github.com/pgvector/pgvector.git
cd pgvector
make
sudo make install
```

### 2. Create Database

```bash
# Login to PostgreSQL
psql -U postgres

# Create database
CREATE DATABASE semantic_job_matcher;

# Exit psql
\q
```

### 3. Install Dependencies

```bash
npm install
```

### 4. Configure Environment

Create a `.env` file in the root directory:

```env
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here

# Database Configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME=semantic_job_matcher
DB_USER=postgres
DB_PASSWORD=your_password_here

# Server Configuration
PORT=3000
NODE_ENV=development

# Upload Configuration
MAX_FILE_SIZE=5242880
```

### 5. Initialize Database Schema

```bash
npm run init-db
```

This will:
- Enable pgvector extension
- Create tables (jobs, resumes, job_matches)
- Create vector similarity indexes

### 6. Seed Sample Jobs

```bash
npm run seed
```

This will populate the database with 10 sample job postings with embeddings.

### 7. Start the Server

```bash
# Development mode (with auto-reload)
npm run dev

# Production mode
npm start
```

The server will start on `http://localhost:3000`

## 📡 API Endpoints

### 1. Health Check
```http
GET /health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-01T00:00:00.000Z"
}
```

### 2. Upload Resume and Get Matches
```http
POST /api/upload-resume
Content-Type: multipart/form-data
```

**Parameters:**
- `resume` (file, required): PDF resume file
- `threshold` (number, optional): Minimum similarity score (0-1, default: 0.5)
- `limit` (number, optional): Maximum matches to return (default: 10)

**Example using curl:**
```bash
curl -X POST http://localhost:3000/api/upload-resume \
  -F "resume=@/path/to/resume.pdf" \
  -F "threshold=0.6" \
  -F "limit=5"
```

**Response:**
```json
{
  "success": true,
  "resumeId": 1,
  "extractedSkills": ["JavaScript", "React", "Node.js", "PostgreSQL"],
  "experienceYears": 5,
  "matchCount": 5,
  "matches": [
    {
      "jobId": 1,
      "title": "Senior Full Stack Developer",
      "company": "TechCorp Solutions",
      "description": "...",
      "requiredSkills": ["JavaScript", "React", "Node.js", "PostgreSQL"],
      "semanticSimilarity": 0.892,
      "skillMatchScore": 95,
      "combinedScore": 0.909,
      "directMatches": ["JavaScript", "React", "Node.js"],
      "relatedMatches": [
        {
          "candidateSkill": "Express",
          "jobSkill": "Node.js",
          "reasoning": "Express is a Node.js framework"
        }
      ],
      "missingSkills": ["Docker"],
      "matchReasoning": "Strong technical fit with direct experience..."
    }
  ]
}
```

### 3. Get Stored Matches for Resume
```http
GET /api/resume/:id/matches
```

**Example:**
```bash
curl http://localhost:3000/api/resume/1/matches
```

### 4. Get All Jobs
```http
GET /api/jobs
```

**Response:**
```json
{
  "success": true,
  "count": 10,
  "jobs": [...]
}
```

### 5. Get Specific Job
```http
GET /api/jobs/:id
```

## 🧪 Testing the System

### Using Postman or Insomnia

1. Create a new POST request to `http://localhost:3000/api/upload-resume`
2. Set body type to `form-data`
3. Add key `resume` with type `File` and select a PDF resume
4. Add optional parameters `threshold` and `limit`
5. Send request

### Using Python Script

```python
import requests

url = 'http://localhost:3000/api/upload-resume'
files = {'resume': open('sample_resume.pdf', 'rb')}
data = {'threshold': 0.6, 'limit': 5}

response = requests.post(url, files=files, data=data)
print(response.json())
```

### Using JavaScript/Node.js

```javascript
const FormData = require('form-data');
const fs = require('fs');
const axios = require('axios');

const form = new FormData();
form.append('resume', fs.createReadStream('sample_resume.pdf'));
form.append('threshold', '0.6');
form.append('limit', '5');

axios.post('http://localhost:3000/api/upload-resume', form, {
  headers: form.getHeaders()
})
.then(response => console.log(response.data))
.catch(error => console.error(error));
```

## 🏗️ Project Structure

```
semantic/
├── src/
│   ├── config/
│   │   └── database.js          # PostgreSQL connection config
│   ├── services/
│   │   ├── pdfExtractor.js      # PDF parsing and text extraction
│   │   ├── embeddingService.js  # OpenAI embedding generation
│   │   └── jobMatchingService.js # Job matching logic
│   ├── scripts/
│   │   ├── initDatabase.js      # Database schema initialization
│   │   └── seedJobs.js          # Sample job data seeding
│   └── server.js                # Express API server
├── uploads/                     # Temporary resume uploads (auto-created)
├── package.json
├── .env                         # Environment configuration
├── .gitignore
└── README.md
```

## 🔧 Configuration Options

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `OPENAI_API_KEY` | Your OpenAI API key | Required |
| `DB_HOST` | PostgreSQL host | localhost |
| `DB_PORT` | PostgreSQL port | 5432 |
| `DB_NAME` | Database name | semantic_job_matcher |
| `DB_USER` | Database user | postgres |
| `DB_PASSWORD` | Database password | Required |
| `PORT` | Server port | 3000 |
| `MAX_FILE_SIZE` | Max upload size in bytes | 5242880 (5MB) |

## 📊 Database Schema

### Tables

#### jobs
- `id`: Primary key
- `title`: Job title
- `company`: Company name
- `description`: Job description
- `required_skills`: Array of required skills
- `preferred_skills`: Array of preferred skills
- `experience_years`: Required years of experience
- `location`: Job location
- `salary_range`: Salary range
- `employment_type`: Full-time, Part-time, Contract
- `embedding`: Vector embedding (1536 dimensions)
- `created_at`, `updated_at`: Timestamps

#### resumes
- `id`: Primary key
- `filename`: Original filename
- `extracted_text`: Full text from PDF
- `parsed_data`: JSON with structured data (skills, experience, etc.)
- `embedding`: Vector embedding (1536 dimensions)
- `created_at`: Timestamp

#### job_matches
- `id`: Primary key
- `resume_id`: Foreign key to resumes
- `job_id`: Foreign key to jobs
- `similarity_score`: Combined similarity score
- `matched_skills`: Array of matched skills
- `created_at`: Timestamp

## 🎓 How Semantic Search Works

### 1. Embedding Generation
- Converts text (resume/job) into 1536-dimensional vectors
- Similar meanings result in similar vectors
- Uses OpenAI's `text-embedding-3-small` model

### 2. Cosine Similarity
- Measures angle between vectors
- Score ranges from 0 (dissimilar) to 1 (identical)
- Formula: `similarity = 1 - (embedding1 <=> embedding2)`

### 3. Combined Scoring
- **70%** Semantic similarity (vector distance)
- **30%** GPT skill match analysis
- Ensures both meaning and explicit skills are considered

## 🤝 Contributing

Feel free to submit issues and enhancement requests!

## 📝 License

MIT License

## 🙏 Acknowledgments

- OpenAI for embeddings and GPT models
- pgvector for PostgreSQL vector similarity search
- pdf-parse for PDF text extraction

## 📞 Support

For issues or questions, please create an issue in the repository.

---

**Happy Job Matching! 🎯**

