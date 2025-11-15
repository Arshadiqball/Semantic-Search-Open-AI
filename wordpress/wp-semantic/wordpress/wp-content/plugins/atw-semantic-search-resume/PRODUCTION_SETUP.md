# Production Setup

## API Server Configuration

Your Node.js API is now configured for production use:

- **Production API URL:** `https://54.183.65.104:3002`
- **CORS:** Configured to allow requests from any origin (clients authenticate via API key)
- **SSL:** Using HTTPS with SSL certificates

## Plugin Configuration

The WordPress plugin is now configured to use the production API:

- **Default API Base URL:** `https://54.183.65.104:3002`
- **SSL Verification:** Disabled (for self-signed certificates)
- **Timeout:** 30-60 seconds depending on operation

## Client Setup

When clients install the plugin:

1. **Activate Plugin** - Plugin will attempt to auto-register
2. **Configure Settings:**
   - API Base URL: `https://54.183.65.104:3002` (pre-filled)
   - Adjust threshold and job count as needed
   - Set job categories and tech stack preferences
3. **Register with API:**
   - Click "Re-register with API" if auto-registration failed
   - Client will receive unique Client ID and API Key
4. **Use the Plugin:**
   - Add shortcode `[atw_semantic_job_search]` to any page
   - Users can upload resumes and get job matches

## API Endpoints Used

The plugin uses these endpoints:

1. **POST /api/admin/clients** - Register new client
2. **POST /api/upload-resume** - Upload and process resume
3. **GET /api/jobs** - Get available jobs (future use)
4. **GET /api/analytics** - Get analytics (future use)

All requests include:
- `X-API-Key` header with client's API key
- Proper authentication
- Error handling

## Security Notes

- API keys are stored securely in WordPress options
- All API communication uses HTTPS
- SSL verification is disabled for self-signed certs (enable for production SSL)
- CORS allows all origins (authentication via API key)

## Troubleshooting

### Connection Issues

If clients can't connect:
1. Verify API server is running: `curl https://54.183.65.104:3002/health`
2. Check firewall rules allow port 3002
3. Verify SSL certificates are valid
4. Check server logs for errors

### SSL Certificate Errors

If you get SSL errors:
- For self-signed certificates: `sslverify` is set to `false` (current)
- For valid SSL certificates: Change `sslverify` to `true` in plugin code

### CORS Issues

CORS is configured to allow all origins. If you need to restrict:
- Update `src/server.js` CORS configuration
- Add specific allowed origins

## Testing

Test the production API:
```bash
curl https://54.183.65.104:3002/health
```

Test client registration:
```bash
curl -X POST https://54.183.65.104:3002/api/admin/clients \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Client", "apiUrl": "https://example.com"}'
```

## Next Steps

1. Ensure API server is running and accessible
2. Test plugin installation on a WordPress site
3. Verify client registration works
4. Test resume upload functionality
5. Monitor server logs for any issues

