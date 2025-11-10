# Quick Start Guide

Get up and running with Semantic Job Matcher in 5 minutes!

## Prerequisites

- **Node.js 18+**: Download from [nodejs.org](https://nodejs.org/)
- **PostgreSQL 14+**: 
  - macOS: `brew install postgresql@14`
  - Ubuntu: `sudo apt-get install postgresql`
- **pgvector extension**: See [installation guide](https://github.com/pgvector/pgvector#installation)
- **OpenAI API Key**: Get from [platform.openai.com](https://platform.openai.com/api-keys)

## Automated Setup (Recommended)

Run the setup script:

```bash
chmod +x setup.sh
./setup.sh
```

The script will:
1. Check prerequisites
2. Install dependencies
3. Create `.env` file
4. Initialize database
5. Optionally seed sample jobs

**Important:** Edit `.env` and add your OpenAI API key before continuing!

## Manual Setup

### 1. Install Dependencies

```bash
npm install
```

### 2. Configure Environment

Create `.env` file:

```env
OPENAI_API_KEY=sk-your-key-here
DB_HOST=localhost
DB_PORT=5432
DB_NAME=semantic_job_matcher
DB_USER=postgres
DB_PASSWORD=your_password
PORT=3000
```

### 3. Create Database

```bash
# Login to PostgreSQL
psql -U postgres

# Create database
CREATE DATABASE semantic_job_matcher;

# Exit
\q
```

### 4. Initialize Schema

```bash
npm run init-db
```

### 5. Seed Sample Data

```bash
npm run seed
```

## Running the Server

### Development Mode (with auto-reload)

```bash
npm run dev
```

### Production Mode

```bash
npm start
```

Server will start at `http://localhost:3000`

## Testing the API

### Method 1: Web Interface (Easiest)

1. Open `test-client.html` in your browser
2. Click "Choose Resume PDF"
3. Select a PDF resume
4. Adjust threshold and limit if needed
5. Click "Upload & Find Matches"
6. View results with match scores and analysis

### Method 2: cURL

```bash
curl -X POST http://localhost:3000/api/upload-resume \
  -F "resume=@/path/to/resume.pdf" \
  -F "threshold=0.6" \
  -F "limit=5"
```

### Method 3: Postman

1. Create POST request to `http://localhost:3000/api/upload-resume`
2. Select Body → form-data
3. Add key `resume` (type: File) and select PDF
4. Add optional keys: `threshold` (0.6), `limit` (5)
5. Send request

### Method 4: JavaScript/Fetch

```javascript
const formData = new FormData();
formData.append('resume', fileInput.files[0]);
formData.append('threshold', '0.6');
formData.append('limit', '5');

fetch('http://localhost:3000/api/upload-resume', {
  method: 'POST',
  body: formData
})
.then(res => res.json())
.then(data => console.log(data));
```

## Understanding the Response

```json
{
  "success": true,
  "resumeId": 1,
  "extractedSkills": ["JavaScript", "React", "Node.js"],
  "experienceYears": 5,
  "matchCount": 3,
  "matches": [
    {
      "jobId": 1,
      "title": "Senior Full Stack Developer",
      "company": "TechCorp Solutions",
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
      "missingSkills": ["Docker", "AWS"],
      "matchReasoning": "Strong technical fit..."
    }
  ]
}
```

### Key Fields

- **semanticSimilarity**: Vector similarity score (0-1)
- **skillMatchScore**: GPT-analyzed skill match (0-100)
- **combinedScore**: Weighted combination (70% semantic + 30% skill)
- **directMatches**: Exact skill overlaps
- **relatedMatches**: Transferable skills identified by AI
- **missingSkills**: Skills candidate should learn

## Example Use Cases

### 1. High Threshold for Exact Matches

```bash
curl -X POST http://localhost:3000/api/upload-resume \
  -F "resume=@resume.pdf" \
  -F "threshold=0.8" \
  -F "limit=3"
```

Returns only very close matches.

### 2. Lower Threshold for More Opportunities

```bash
curl -X POST http://localhost:3000/api/upload-resume \
  -F "resume=@resume.pdf" \
  -F "threshold=0.4" \
  -F "limit=20"
```

Returns more jobs including stretch opportunities.

### 3. Getting Stored Matches

```bash
curl http://localhost:3000/api/resume/1/matches
```

Retrieve previously calculated matches for resume ID 1.

## Common Issues

### "Connection refused" Error

- Make sure the server is running: `npm start`
- Check if port 3000 is available
- Try a different port in `.env`

### "Database connection failed"

- Verify PostgreSQL is running: `brew services start postgresql@14`
- Check credentials in `.env`
- Ensure database exists: `psql -U postgres -l`

### "pgvector extension not found"

- Install pgvector: [https://github.com/pgvector/pgvector](https://github.com/pgvector/pgvector)
- Run `npm run init-db` again

### "OpenAI API error"

- Verify your API key is correct in `.env`
- Check you have credits: [platform.openai.com/account/usage](https://platform.openai.com/account/usage)
- Ensure no spaces in the key

### "No matching jobs found"

- Lower the threshold (try 0.3-0.4)
- Ensure jobs are seeded: `npm run seed`
- Check if resume PDF is text-based (not scanned image)

## What Makes This "Semantic"?

Unlike keyword matching, this system:

1. **Understands Context**: "React developer" matches "Frontend Engineer" jobs
2. **Recognizes Related Skills**: Laravel experience → knows PHP
3. **Infers Capabilities**: Express.js → Node.js backend experience
4. **Semantic Similarity**: Uses meaning, not just text matching

### Example

**Resume has:** Laravel, Vue.js, MySQL  
**Job requires:** PHP, Frontend framework, Database

**Traditional system:** ❌ No match (different keywords)  
**Semantic system:** ✅ 85% match (understands relationships)

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| POST | `/api/upload-resume` | Upload resume, get matches |
| GET | `/api/resume/:id/matches` | Get stored matches |
| GET | `/api/jobs` | List all jobs |
| GET | `/api/jobs/:id` | Get specific job |

## Next Steps

1. **Add More Jobs**: Edit `src/scripts/seedJobs.js` and run `npm run seed`
2. **Customize Matching**: Adjust weights in `src/services/jobMatchingService.js`
3. **Improve Parsing**: Enhance skill detection in `src/services/pdfExtractor.js`
4. **Build Frontend**: Create a React/Vue app using the API
5. **Deploy**: Use Docker + PostgreSQL on AWS/Heroku

## Tips for Best Results

- **Resume Quality**: Use text-based PDFs (not scanned images)
- **Clear Skills Section**: List technologies explicitly
- **Experience Section**: Include years and specific technologies used
- **Projects**: Mention technologies used in each project

## Need Help?

- Check the main [README.md](README.md) for detailed documentation
- Review [API documentation](#api-endpoints-summary)
- Check server logs for errors: `npm start`
- Ensure all environment variables are set correctly

---

**Happy Job Matching! 🎯**

