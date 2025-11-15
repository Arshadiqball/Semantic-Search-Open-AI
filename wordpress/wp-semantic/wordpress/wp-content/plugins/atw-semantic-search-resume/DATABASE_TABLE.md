# Database Table Structure

## Custom Settings Table

The plugin creates a custom database table to store all preferences and API connection details.

### Table Name
`{wp_prefix}_atw_semantic_settings`

Example: `wp_atw_semantic_settings`

### Table Structure

```sql
CREATE TABLE wp_atw_semantic_settings (
    id int(11) NOT NULL AUTO_INCREMENT,
    setting_key varchar(100) NOT NULL,
    setting_value longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
);
```

### Stored Settings

The following settings are stored in the table:

| Setting Key | Type | Description |
|------------|------|-------------|
| `api_base_url` | string | Node.js API base URL |
| `threshold` | float | Similarity threshold (0.0-1.0) |
| `recommended_jobs_count` | integer | Number of jobs to show |
| `job_categories` | JSON array | Selected job categories |
| `tech_stack` | JSON array | Preferred tech stack |
| `client_id` | string | Client ID from API |
| `api_key` | string | API key from API |
| `is_registered` | boolean | Registration status |
| `registration_ip` | string | IP address at registration |
| `registration_email` | string | Admin email at registration |
| `registration_error` | string | Last registration error |

### Default Values

**All settings start empty/null by default.** No default values are pre-filled.

### Data Storage

- **Simple values** (strings, numbers, booleans): Stored as-is
- **Arrays/Objects**: Stored as JSON strings, automatically decoded when retrieved

### Benefits

1. **Isolated Storage**: All plugin data in one dedicated table
2. **Easy Backup**: Single table to backup/restore
3. **Clean Database**: Doesn't clutter WordPress options table
4. **Better Performance**: Direct table queries instead of options API
5. **Audit Trail**: `created_at` and `updated_at` timestamps

### Accessing Settings

Settings are accessed through plugin methods:
- `$plugin->get_setting('key', $default)` - Get setting
- `$plugin->save_setting('key', $value)` - Save setting
- `$plugin->delete_setting('key')` - Delete setting

### Migration from Options

If you had settings stored in WordPress options, you can migrate them:

```php
// Example migration script
$old_options = array(
    'atw_semantic_api_base_url',
    'atw_semantic_threshold',
    // ... etc
);

foreach ($old_options as $option) {
    $value = get_option($option);
    if ($value !== false) {
        $key = str_replace('atw_semantic_', '', $option);
        $plugin->save_setting($key, $value);
        delete_option($option); // Clean up old option
    }
}
```

