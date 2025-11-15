# Multi-Tenant SaaS Architecture

This application has been transformed into a multi-tenant SaaS product where each client has their own isolated data and API access.

## Architecture Overview

- **Clients Table**: Stores client information (name, API key, API URL, etc.)
- **Tenant Isolation**: All data (jobs, resumes, job_matches) is scoped by `client_id`
- **API Key Authentication**: Clients authenticate using API keys
- **Separate Databases**: Each client's data is isolated within the same database using `client_id` foreign keys

## Database Schema

### Clients Table
- `id` - Primary key
- `client_id` - Unique identifier (e.g., "acme-corp-abc123")
- `name` - Client name
- `api_key` - Secure API key for authentication
- `api_url` - Optional API URL for the client
- `is_active` - Whether the client account is active

### Tenant-Aware Tables
All tables now include `client_id`:
- `jobs.client_id` - Links jobs to clients
- `resumes.client_id` - Links resumes to clients
- `job_matches.client_id` - Links matches to clients

## API Endpoints

### Admin Endpoints (No Authentication Required)

#### Create Client
```bash
POST /api/admin/clients
Content-Type: application/json

{
  "name": "Acme Corporation",
  "apiUrl": "https://acme.com/api"
}
```

Response:
```json
{
  "success": true,
  "client": {
    "id": 1,
    "clientId": "acme-corporation-abc123",
    "name": "Acme Corporation",
    "apiKey": "sk_...",
    "apiUrl": "https://acme.com/api",
    "isActive": true
  }
}
```

#### Get All Clients
```bash
GET /api/admin/clients
```

#### Get Client by ID
```bash
GET /api/admin/clients/:id
```

#### Update Client
```bash
PUT /api/admin/clients/:id
Content-Type: application/json

{
  "name": "Updated Name",
  "apiUrl": "https://new-url.com",
  "is_active": true
}
```

#### Regenerate API Key
```bash
POST /api/admin/clients/:id/regenerate-key
```

### Client API Endpoints (Requires API Key)

All client endpoints require authentication via API key. Provide it in one of these ways:

1. **Header**: `X-API-Key: sk_...`
2. **Authorization Header**: `Authorization: Bearer sk_...`
3. **Query Parameter**: `?api_key=sk_...`

#### Upload Resume
```bash
POST /api/upload-resume
X-API-Key: sk_...
Content-Type: multipart/form-data

FormData:
- resume: <PDF file>
- email: user@example.com (optional)
- threshold: 0.5 (optional)
- limit: 10 (optional)
```

#### Get Jobs
```bash
GET /api/jobs
X-API-Key: sk_...
```

#### Get Job by ID
```bash
GET /api/jobs/:id
X-API-Key: sk_...
```

#### Get Resume Matches
```bash
GET /api/resume/:id/matches?limit=10
X-API-Key: sk_...
```

#### Get Analytics
```bash
GET /api/analytics
X-API-Key: sk_...
```

## Setup Instructions

### 1. Run Database Migration

```bash
npm run migrate
```

This will:
- Create the `clients` table
- Add `client_id` columns to `jobs`, `resumes`, and `job_matches`
- Create a default client for existing data
- Migrate existing data to the default client

### 2. Create Clients

Use the admin API to create clients:

```bash
curl -X POST http://localhost:3000/api/admin/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Client 1",
    "apiUrl": "https://client1.com"
  }'
```

Save the returned `apiKey` - you'll need it for client authentication.

### 3. Seed Jobs for Clients

When seeding jobs, make sure to assign them to a specific client:

```javascript
// Example: Seed jobs for client ID 1
const clientId = 1;
await pool.query(`
  INSERT INTO jobs (title, company, ..., client_id)
  VALUES ($1, $2, ..., $3)
`, [title, company, ..., clientId]);
```

### 4. Client Integration

Clients should use their API key to make requests:

```javascript
// Example: Client uploads resume
const formData = new FormData();
formData.append('resume', pdfFile);
formData.append('email', 'user@example.com');

const response = await fetch('https://your-api.com/api/upload-resume', {
  method: 'POST',
  headers: {
    'X-API-Key': 'sk_client_api_key_here'
  },
  body: formData
});
```

## Data Isolation

- Each client can only see their own:
  - Jobs
  - Resumes
  - Job matches
  - Analytics

- Database queries automatically filter by `client_id` based on the authenticated client
- No client can access another client's data

## Security

- API keys are securely generated using `crypto.randomBytes`
- API keys are stored as hashed values (consider implementing hashing in production)
- All client endpoints require valid API key authentication
- Inactive clients cannot access the API

## Migration Notes

- Existing data is automatically assigned to a "default" client
- The default client has a generated API key
- You can create new clients and migrate data as needed

## Example: Creating 10 Clients

```bash
# Create 10 clients
for i in {1..10}; do
  curl -X POST http://localhost:3000/api/admin/clients \
    -H "Content-Type: application/json" \
    -d "{\"name\": \"Client $i\", \"apiUrl\": \"https://client$i.com\"}"
done
```

Each client will receive:
- A unique `client_id` (e.g., "client-1-abc123")
- A unique `api_key` (e.g., "sk_...")
- Their own isolated data space

## Troubleshooting

### "Unauthorized" Error
- Check that the API key is correct
- Ensure the client account is active (`is_active = true`)
- Verify the API key is being sent in the request

### "Resume not found" Error
- Ensure the resume belongs to the authenticated client
- Check that `client_id` matches between resume and client

### "Job not found" Error
- Ensure the job belongs to the authenticated client
- Verify jobs are seeded with the correct `client_id`

