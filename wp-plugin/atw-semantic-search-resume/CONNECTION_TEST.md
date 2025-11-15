# Connection Test Guide

## Testing WordPress Plugin Connection to Node.js API

### Prerequisites
1. Node.js API running on `http://localhost:3000`
2. WordPress running on `http://localhost:8080`
3. Plugin installed and activated in WordPress

### Step 1: Verify Node.js API is Running

```bash
# Check if API is running
curl http://localhost:3000/health

# Expected response:
# {"status":"ok","timestamp":"..."}
```

### Step 2: Activate Plugin in WordPress

1. Go to `http://localhost:8080/wp-admin`
2. Navigate to **Plugins**
3. Find **ATW Semantic Search Resume**
4. Click **Activate**

### Step 3: Check Plugin Registration

After activation, the plugin should automatically register with the API.

1. Go to **WordPress Admin → Semantic Search**
2. Check the **Client Information** section:
   - Registration Status should show "Registered" ✅
   - Client ID should be displayed
   - API Key should be displayed

If registration failed:
- Check WordPress error logs
- Verify Node.js API is accessible from WordPress container
- Click "Re-register with API" button

### Step 4: Test API Connection from WordPress

You can test the connection using WordPress's built-in HTTP functions:

**Option 1: Via WordPress Admin**
1. Go to **Semantic Search → Settings**
2. Click "Re-register with API"
3. Check for success/error message

**Option 2: Via WP-CLI (if available)**
```bash
docker exec -it <wordpress-container> wp option get atw_semantic_api_key
```

### Step 5: Test Resume Upload

1. Create a test page in WordPress
2. Add shortcode: `[atw_semantic_job_search]`
3. Visit the page
4. Upload a test PDF resume
5. Check if job matches are returned

### Troubleshooting

#### Issue: "API key not configured"
**Solution:**
- Go to Semantic Search settings
- Click "Re-register with API"
- Verify API Base URL is `http://localhost:3000`

#### Issue: "Failed to register with API"
**Possible causes:**
1. Node.js API not running
   - Start API: `npm run local`
   
2. Network connectivity
   - WordPress container can't reach host machine
   - Try using `host.docker.internal:3000` instead of `localhost:3000`
   
3. CORS issues
   - Check browser console for CORS errors
   - Verify CORS is configured in local-server.js

#### Issue: "Connection refused" or "Connection timeout"
**Solution:**
- If WordPress is in Docker, use `host.docker.internal` instead of `localhost`
- Update API Base URL in settings to: `http://localhost:3000`

#### Issue: Resume upload fails
**Check:**
1. API key is set correctly
2. File is PDF format
3. File size < 5MB
4. Node.js API logs for errors

### Docker-Specific Notes

If WordPress is running in Docker (which it is based on docker-compose.yml):

**From WordPress container, `localhost:3000` won't work!**

Use one of these options:

1. **Use host.docker.internal (Recommended)**
   - Update API Base URL to: `http://localhost:3000`
   - This works on Docker Desktop for Mac/Windows

2. **Use host network mode**
   - Modify docker-compose.yml to use host network
   - Not recommended for production

3. **Use Docker service name**
   - If Node.js API is also in Docker, use service name
   - Requires both in same docker-compose network

### Verification Commands

```bash
# Test API from host machine
curl http://localhost:3000/health

# Test API from WordPress container
docker exec -it <wordpress-container> curl http://localhost:3000/health

# Check WordPress options
docker exec -it <wordpress-container> wp option get atw_semantic_api_key
docker exec -it <wordpress-container> wp option get atw_semantic_client_id
```

### Expected WordPress Options

After successful registration, these options should be set:
- `atw_semantic_api_base_url`: `http://localhost:3000` (or `http://localhost:3000`)
- `atw_semantic_client_id`: Client ID from API
- `atw_semantic_api_key`: API key from API
- `atw_semantic_is_registered`: `1` (true)

