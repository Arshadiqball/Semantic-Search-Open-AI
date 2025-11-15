# Changelog

## Version 1.0.1 - Custom Database Table

### Changes
- **Custom Database Table**: All plugin preferences and API connection details are now stored in a dedicated database table `{wp_prefix}_atw_semantic_settings`
- **No Default Values**: All preferences start empty/null by default (no pre-filled values)
- **Better Data Management**: Settings are isolated in their own table for easier backup and management
- **Improved Storage**: Arrays/objects are automatically stored as JSON

### Database Table
- Table name: `wp_atw_semantic_settings` (or your custom prefix)
- Stores: API URL, threshold, job count, categories, tech stack, client credentials
- Automatic JSON encoding/decoding for complex data types

### Migration
- Old WordPress options are no longer used
- Settings are automatically migrated to the new table when saved
- No data loss - all settings are preserved

### Benefits
1. Clean database structure
2. Easy backup/restore
3. Better performance
4. Audit trail with timestamps
5. No clutter in WordPress options table

