# WordPress Jobs Integration

## Overview

This system allows each WordPress client to manage their own jobs in their WordPress database (`wp_jobs` table), and the Node.js server fetches and processes these jobs for semantic search.

## Architecture

1. **WordPress Database (`wp_jobs` table)**
   - Each client manages jobs in their own WordPress database
   - Table: `{prefix}_jobs` (e.g., `wp_jobs`)
   - Jobs are stored with all details (title, company, description, skills, etc.)

2. **Node.js Server Processing**
   - Fetches jobs from WordPress database
   - Generates embeddings for semantic search
   - Stores processed jobs in Node.js database with `wp_job_id` reference
   - Links jobs to client via `client_id`

3. **Sync Process**
   - WordPress plugin sends database credentials to Node.js API
   - Node.js connects to WordPress MySQL database
   - Fetches jobs and processes them
   - Updates Node.js database with embeddings

## WordPress Table Structure

```sql
CREATE TABLE wp_jobs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    company varchar(255) NOT NULL,
    description longtext NOT NULL,
    required_skills text,
    preferred_skills text,
    experience_years int(11) DEFAULT NULL,
    location varchar(255) DEFAULT NULL,
    salary_range varchar(100) DEFAULT NULL,
    employment_type varchar(50) DEFAULT 'Full-time',
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
);
```

## Node.js Database Structure

The Node.js `jobs` table includes:
- All job fields from WordPress
- `client_id` - Links to client
- `wp_job_id` - Reference to WordPress job ID
- `embedding` - Vector embedding for semantic search

## Usage

### 1. Add Jobs in WordPress

Jobs can be added to `wp_jobs` table via:
- WordPress admin interface (if you create one)
- Direct database insertion
- WordPress REST API
- Plugin functions: `ATW_Jobs_Manager::save_job()`

### 2. Sync Jobs to Node.js

In WordPress Admin → ATW Semantic Search → Settings:
- Click "Sync Jobs to Node.js Server"
- Plugin sends WordPress DB credentials to Node.js
- Node.js fetches jobs, generates embeddings, stores in Node.js DB

### 3. Resume Matching

When a resume is uploaded:
- Node.js uses jobs from its database (synced from WordPress)
- Performs semantic search matching
- Returns matched jobs

## API Endpoints

### POST `/api/sync-wordpress-jobs`
- Requires: API Key authentication
- Body: WordPress database credentials
  ```json
  {
    "db_host": "localhost",
    "db_port": 3306,
    "db_name": "wordpress_db",
    "db_user": "wp_user",
    "db_password": "wp_password",
    "table_prefix": "wp_"
  }
  ```
- Response:
  ```json
  {
    "success": true,
    "total": 50,
    "processed": 50,
    "created": 45,
    "updated": 5
  }
  ```

## Security Notes

- WordPress database credentials are sent over HTTPS
- Credentials are only used for the sync operation
- Node.js server must have network access to WordPress database
- Consider using read-only database user for sync operations

## Migration

Run migration to add `wp_job_id` column:
```bash
npm run migrate
```

This adds the `wp_job_id` column to the Node.js `jobs` table to link with WordPress jobs.

