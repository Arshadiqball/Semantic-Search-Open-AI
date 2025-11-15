# ATW Semantic Search Resume - WordPress Plugin

A WordPress plugin that integrates with the Node.js multi-tenant semantic job matching API.

## Plugin Structure

```
wp-plugin/
├── atw-semantic-search-resume.php  # Main plugin file
├── templates/
│   ├── admin-settings.php          # Admin settings page
│   └── job-search.php              # Frontend job search form
├── assets/
│   ├── admin.css                   # Admin styles
│   ├── admin.js                    # Admin JavaScript
│   ├── frontend.css                # Frontend styles
│   └── frontend.js                 # Frontend JavaScript
├── readme.txt                      # WordPress.org readme
├── INSTALLATION.md                 # Installation guide
└── README.md                       # This file
```

## Features

### 1. Automatic Client Registration
- On plugin activation, automatically registers WordPress site with Node.js API
- Retrieves unique Client ID and API Key
- Stores IP address and admin email for analytics
- Can re-register via admin panel if needed

### 2. Admin Settings Panel
Located at: **WordPress Admin → Semantic Search**

**Settings Include:**
- **API Configuration**
  - API Base URL (configurable)
  
- **Client Information** (Read-only)
  - Registration Status
  - Client ID
  - API Key
  
- **Search Preferences**
  - Similarity Threshold (0.0 - 1.0)
  - Recommended Jobs Count (1-50)
  
- **Job Categories**
  - Multi-select checkboxes for default categories
  - Common categories pre-populated
  
- **Tech Stack Preferences**
  - Textarea for entering preferred technologies
  - One technology per line

### 3. Frontend Job Search
- Shortcode: `[atw_semantic_job_search]`
- Resume upload form (PDF only, max 5MB)
- Email collection
- AJAX-based job matching
- Beautiful, responsive UI
- Displays job recommendations with:
  - Job title and company
  - Match score percentage
  - Location, experience, salary
  - Required skills
  - Job description

## How It Works

### 1. Plugin Activation Flow

```
User Activates Plugin
    ↓
register_activation_hook() fires
    ↓
register_with_api() called
    ↓
POST to /api/admin/clients
    ↓
Node.js API creates client
    ↓
Returns Client ID + API Key
    ↓
Stored in WordPress options
```

### 2. Resume Upload Flow

```
User uploads resume
    ↓
Frontend JavaScript validates file
    ↓
AJAX POST to WordPress
    ↓
WordPress validates & forwards to Node.js API
    ↓
Node.js API processes resume
    ↓
Returns job matches
    ↓
Frontend displays results
```

### 3. API Communication

All API calls include:
- `X-API-Key` header with client's API key
- Proper authentication
- Error handling
- SSL verification (configurable)

## WordPress Options Stored

- `atw_semantic_api_base_url` - API server URL
- `atw_semantic_client_id` - Client identifier
- `atw_semantic_api_key` - API authentication key
- `atw_semantic_threshold` - Similarity threshold
- `atw_semantic_recommended_jobs_count` - Number of jobs to show
- `atw_semantic_job_categories` - Selected categories (array)
- `atw_semantic_tech_stack` - Preferred technologies (array)
- `atw_semantic_is_registered` - Registration status (boolean)
- `atw_semantic_registration_ip` - IP address at registration
- `atw_semantic_registration_email` - Admin email at registration

## Security Features

1. **Nonce Verification** - All AJAX requests verified
2. **File Validation** - Type and size checking
3. **Capability Checks** - Admin functions require `manage_options`
4. **Sanitization** - All inputs sanitized
5. **API Key Protection** - Stored securely, never exposed to frontend

## Integration with Node.js API

### Endpoints Used

1. **POST /api/admin/clients** - Register new client
2. **POST /api/upload-resume** - Upload and process resume
3. **GET /api/jobs** - Get available jobs (future use)

### Request Format

**Resume Upload:**
```
POST /api/upload-resume
Headers:
  X-API-Key: sk_...
  Content-Type: multipart/form-data
Body:
  - resume: PDF file
  - email: user@example.com
  - threshold: 0.5
  - limit: 10
```

## Customization

### Styling
- Modify `assets/frontend.css` for frontend styles
- Modify `assets/admin.css` for admin styles

### Functionality
- Modify `atw-semantic-search-resume.php` for core functionality
- Modify `assets/frontend.js` for frontend behavior
- Modify templates for UI changes

### Shortcode Options
```php
[atw_semantic_job_search title="Custom Title" show_upload="yes"]
```

## Error Handling

The plugin handles:
- Network errors
- API authentication failures
- File validation errors
- Missing configuration
- Invalid responses

All errors are displayed user-friendly messages.

## Future Enhancements

Potential features:
- Job filtering by category/tech stack
- Saved searches
- Email notifications
- Analytics dashboard
- Resume history
- Multiple resume uploads

## Support

For issues or questions:
- Check WordPress error logs
- Check Node.js API logs
- Verify API connectivity
- Review plugin settings

## License

GPL v2 or later

