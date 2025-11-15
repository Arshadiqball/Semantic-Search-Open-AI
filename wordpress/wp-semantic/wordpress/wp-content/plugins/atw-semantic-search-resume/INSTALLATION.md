# WordPress Plugin Installation Guide

## Installation Steps

### 1. Upload Plugin to WordPress

1. **Via WordPress Admin (Recommended)**
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin"
   - Select the `atw-semantic-search-resume` folder (zipped)
   - Click "Install Now"
   - Activate the plugin

2. **Via FTP/SFTP**
   - Upload the `atw-semantic-search-resume` folder to `/wp-content/plugins/`
   - Go to WordPress Admin → Plugins
   - Find "ATW Semantic Search Resume" and click "Activate"

### 2. Automatic Registration

When you activate the plugin, it will automatically:
- Register your WordPress site with the Node.js API
- Get a unique Client ID and API Key
- Store these credentials securely in WordPress options

### 3. Configure Settings

1. Go to **WordPress Admin → Semantic Search**
2. Configure the following settings:

   **API Configuration:**
   - API Base URL: Your Node.js API server URL (default: `https://54.183.65.104:3002`)

   **Search Preferences:**
   - Similarity Threshold: 0.0 - 1.0 (default: 0.5)
     - Higher = more strict matching
     - Lower = more lenient matching
   - Recommended Jobs Count: 1-50 (default: 10)

   **Job Categories:**
   - Select default job categories to filter by
   - Check multiple categories as needed

   **Tech Stack Preferences:**
   - Enter preferred technologies (one per line)
   - Example:
     ```
     JavaScript
     React
     Node.js
     Python
     ```

3. Click "Save Settings"

### 4. Display Job Search Form

Add the shortcode to any page or post:

```
[atw_semantic_job_search]
```

**Shortcode Options:**
- `title` - Custom title (default: "Find Your Dream Job")
- `show_upload` - Show resume upload form (yes/no, default: yes)

**Examples:**
```
[atw_semantic_job_search title="Find Your Perfect Job"]
[atw_semantic_job_search show_upload="no"]
```

### 5. Verify Registration

After activation, check:
- **Semantic Search → Settings** in WordPress admin
- Look for "Registration Status" - should show "Registered" with a green checkmark
- Client ID and API Key should be displayed

## Troubleshooting

### Plugin Not Registering

If registration fails:
1. Check that your Node.js API server is running
2. Verify the API Base URL is correct
3. Check WordPress error logs
4. Click "Re-register with API" button in settings

### API Key Not Showing

If API key is empty:
1. Check WordPress database - options table
2. Look for `atw_semantic_api_key` option
3. Try re-registering via settings page

### Resume Upload Not Working

1. Check file size (max 5MB)
2. Verify file is PDF format
3. Check browser console for JavaScript errors
4. Verify API key is set correctly
5. Check Node.js API server logs

### Jobs Not Displaying

1. Ensure jobs are seeded in the Node.js database for your client
2. Check that jobs have the correct `client_id`
3. Verify API key is correct
4. Check similarity threshold setting

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Node.js API server running and accessible
- SSL certificate (recommended for production)

## Security Notes

- API keys are stored in WordPress options table
- Never share your API key publicly
- Use HTTPS for API communication in production
- Regularly update the plugin

## Support

For support, visit: https://atwebtechnologies.com

