# Local Setup Guide

## Quick Setup for Local Development

### Prerequisites
- Node.js API running on `http://localhost:3000`
- WordPress running on `http://localhost:8080` (via Docker)

### Step 1: Start Node.js API

```bash
cd /Users/arshadiqbal/Documents/Faisal/semantic
npm run local
```

The API should be running on `http://localhost:3000`

### Step 2: Start WordPress (if not running)

```bash
cd wordpress/wp-semantic
docker-compose up -d
```

WordPress should be accessible at `http://localhost:8080`

### Step 3: Install Plugin

The plugin is already copied to:
```
wordpress/wp-semantic/wordpress/wp-content/plugins/atw-semantic-search-resume/
```

1. Go to `http://localhost:8080/wp-admin`
2. Navigate to **Plugins**
3. Find **ATW Semantic Search Resume**
4. Click **Activate**

### Step 4: Configure API URL

**IMPORTANT:** Since WordPress is running in Docker, you need to use `host.docker.internal` instead of `localhost`.

1. Go to **WordPress Admin → Semantic Search**
2. In **API Configuration**, set API Base URL to:
   ```
   http://localhost:3000
   ```
3. Click **Save Settings**

### Step 5: Verify Registration

After saving settings:

1. Check **Client Information** section
2. Registration Status should show "Registered" ✅
3. Client ID and API Key should be displayed

If not registered:
- Click **"Re-register with API"** button
- Check WordPress error logs if it fails

### Step 6: Test the Plugin

1. Create a new page in WordPress
2. Add the shortcode: `[atw_semantic_job_search]`
3. Publish the page
4. Visit the page
5. Upload a test PDF resume
6. Check if job matches are displayed

## Troubleshooting

### WordPress can't connect to API

**Problem:** WordPress (in Docker) can't reach `localhost:3000`

**Solution:** Use `host.docker.internal:3000` instead of `localhost:3000`

### CORS Errors

**Problem:** Browser shows CORS errors in console

**Solution:** 
- Verify CORS is configured in `src/local-server.js`
- Check that origin `http://localhost:8080` is allowed

### API Registration Fails

**Check:**
1. Node.js API is running: `curl http://localhost:3000/health`
2. API URL is correct in WordPress settings
3. WordPress error logs for details

### Resume Upload Fails

**Check:**
1. API key is set correctly
2. File is PDF format
3. File size < 5MB
4. Node.js API logs for errors

## Testing Connection

### From Host Machine
```bash
curl http://localhost:3000/health
```

### From WordPress Container
```bash
# Get container name
docker ps

# Test connection
docker exec -it <wordpress-container-name> curl http://localhost:3000/health
```

## Configuration Summary

**WordPress Settings:**
- API Base URL: `http://localhost:3000`
- Similarity Threshold: `0.5` (default)
- Recommended Jobs Count: `10` (default)

**Node.js API:**
- Port: `3000`
- CORS: Enabled for `http://localhost:8080`

## Next Steps

1. Seed some jobs in the Node.js database for your client
2. Test resume upload functionality
3. Customize job categories and tech stack preferences
4. Style the frontend to match your theme

