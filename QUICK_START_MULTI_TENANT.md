# Quick Start: Multi-Tenant Setup

## Overview

Your application is now a multi-tenant SaaS product. Each client has:
- Their own API key
- Their own isolated data (jobs, resumes, matches)
- Their own analytics

## Step 1: Run Migrations

```bash
npm run migrate
```

This creates the clients table and adds tenant isolation to all tables.

## Step 2: Create Clients

### Option A: Use the Script (Recommended)

```bash
npm run create-clients
```

This creates 10 clients with unique API keys. **Save the API keys!**

### Option B: Use the Admin API

```bash
curl -X POST http://localhost:3000/api/admin/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Client",
    "apiUrl": "https://myclient.com"
  }'
```

## Step 3: Seed Jobs for Clients

When seeding jobs, assign them to a client:

```javascript
// Example: Update seedJobs.js to include client_id
const clientId = 1; // Get from clients table
await pool.query(`
  INSERT INTO jobs (title, company, ..., client_id)
  VALUES ($1, $2, ..., $3)
`, [title, company, ..., clientId]);
```

## Step 4: Client Usage

Clients use their API key to access the API:

```bash
# Upload resume
curl -X POST http://localhost:3000/api/upload-resume \
  -H "X-API-Key: sk_client_api_key_here" \
  -F "resume=@resume.pdf" \
  -F "email=user@example.com"

# Get jobs
curl -X GET http://localhost:3000/api/jobs \
  -H "X-API-Key: sk_client_api_key_here"

# Get analytics
curl -X GET http://localhost:3000/api/analytics \
  -H "X-API-Key: sk_client_api_key_here"
```

## API Key Authentication

Clients can provide API keys in three ways:

1. **Header**: `X-API-Key: sk_...`
2. **Authorization Header**: `Authorization: Bearer sk_...`
3. **Query Parameter**: `?api_key=sk_...`

## Admin Endpoints

Manage clients via admin endpoints (no auth required for local development):

- `POST /api/admin/clients` - Create client
- `GET /api/admin/clients` - List all clients
- `GET /api/admin/clients/:id` - Get client details
- `PUT /api/admin/clients/:id` - Update client
- `POST /api/admin/clients/:id/regenerate-key` - Regenerate API key

## Data Isolation

- Each client only sees their own:
  - Jobs
  - Resumes
  - Job matches
  - Analytics

- Data is automatically filtered by `client_id` based on the authenticated client

## Example: Complete Client Workflow

```bash
# 1. Create a client
CLIENT_RESPONSE=$(curl -X POST http://localhost:3000/api/admin/clients \
  -H "Content-Type: application/json" \
  -d '{"name": "Acme Corp", "apiUrl": "https://acme.com"}')

# Extract API key (use jq or parse manually)
API_KEY=$(echo $CLIENT_RESPONSE | jq -r '.client.apiKey')

# 2. Upload a resume
curl -X POST http://localhost:3000/api/upload-resume \
  -H "X-API-Key: $API_KEY" \
  -F "resume=@resume.pdf" \
  -F "email=candidate@example.com"

# 3. Get matches
curl -X GET "http://localhost:3000/api/jobs?limit=10" \
  -H "X-API-Key: $API_KEY"

# 4. View analytics
curl -X GET http://localhost:3000/api/analytics \
  -H "X-API-Key: $API_KEY"
```

## Important Notes

1. **API Keys are Secret**: Treat API keys like passwords. Never commit them to version control.

2. **Client Isolation**: Each client's data is completely isolated. Client A cannot see Client B's data.

3. **Default Client**: Existing data is assigned to a "default" client. You can migrate it later.

4. **Job Seeding**: When seeding jobs, make sure to assign them to the correct client using `client_id`.

5. **Production**: In production, consider:
   - Adding authentication to admin endpoints
   - Hashing API keys
   - Rate limiting per client
   - Monitoring and logging

## Troubleshooting

### "Unauthorized" Error
- Check API key is correct
- Ensure client is active (`is_active = true`)
- Verify API key is sent in request

### "Resume not found" / "Job not found"
- Ensure the resource belongs to the authenticated client
- Check `client_id` matches

### No Data Showing
- Verify jobs are seeded with the correct `client_id`
- Check that you're using the correct API key for the client

