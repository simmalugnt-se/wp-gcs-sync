# GCS Media Sync WordPress Plugin

A WordPress plugin that automatically syncs media uploads to Google Cloud Storage (GCS) and optionally deletes them from your local server to save disk space. This plugin is adapted from the GCS functionality in the Stadshem API.

## Features

- ✅ **Automatic Upload Sync**: All new media uploads are automatically synced to Google Cloud Storage
- ✅ **Image Size Support**: Uploads all WordPress-generated image sizes (thumbnails, medium, large, etc.)
- ✅ **Image Optimization**: Automatic image resizing and quality optimization
- ✅ **URL Rewriting**: Automatically serves images from GCS URLs
- ✅ **Deletion Sync**: When you delete media from WordPress, it's also deleted from GCS
- ✅ **Easy Configuration**: Simple WordPress admin interface for setup
- ✅ **Status Checking**: Built-in diagnostics to verify configuration
- ✅ **WP-CLI Commands**: Bulk sync existing media and individual attachment sync

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Cloud Storage PHP library (installed via Composer)
- A Google Cloud Storage bucket

## Installation

### 1. Install the Plugin

Copy this plugin folder to your WordPress plugins directory:

```bash
wp-content/plugins/gcs-media-sync/
```

### 2. Install Google Cloud Storage Library

You need to install the Google Cloud Storage PHP library. This can be done in several ways:

**Option A: Via Composer (Recommended)**

```bash
cd /path/to/your/wordpress
composer require google/cloud-storage
```

**Option B: Via WordPress Composer**
If you're using a WordPress installation managed by Composer, add it to your `composer.json`:

```json
{
  "require": {
    "google/cloud-storage": "^1.30"
  }
}
```

### 3. Set Up Google Cloud Storage

1. Create a Google Cloud Storage bucket
2. Create a service account with access to the bucket
3. Download the service account JSON key file
4. Set appropriate permissions on the bucket (Storage Object Admin role)

## Configuration

### 1. Activate the Plugin

Go to WordPress Admin → Plugins and activate "GCS Media Sync".

### 2. Configure Settings

Go to WordPress Admin → Settings → GCS Media Sync and configure:

- **Enable GCS Sync**: Check to enable the plugin
- **Bucket Name**: Your Google Cloud Storage bucket name
- **Bucket Folder** (optional): Prefix folder inside the bucket (e.g., "site-uploads")
- **Service Account JSON**: Paste the entire JSON content from your service account key file
- **Max Image Width**: Maximum width for uploaded images (default: 1982px)
- **Image Quality**: JPEG quality setting (default: 85%)
- **Auto-Delete Local Files**: Delete files from server after successful sync to save disk space (⚠️ **Warning**: Files will be permanently removed from your server)

### 3. Verify Configuration

Go to WordPress Admin → Tools → GCS Check to verify that:

- The Google Cloud Storage library is installed
- Your configuration is correct
- The plugin is enabled

## How It Works

### Upload Process

1. When a file is uploaded to WordPress, it's processed normally
2. After WordPress generates all image sizes, the plugin uploads:
   - The original file
   - All generated sizes (thumbnail, medium, large, etc.)
3. Files are stored in GCS with the same directory structure as WordPress
4. The plugin stores GCS URLs in the attachment metadata

### URL Rewriting

- Once uploaded to GCS, all image URLs are automatically rewritten to serve from GCS
- This includes:
  - Direct attachment URLs
  - Image src attributes
  - Responsive image srcset attributes

### Local File Management

The plugin provides two modes for managing local files after sync:

**Default Mode (Auto-Delete Disabled)**:

- Files remain on your server after being synced to GCS
- Provides backup redundancy and compatibility with plugins that expect local files
- URLs are rewritten to serve from GCS while keeping local copies

**Auto-Delete Mode (Auto-Delete Enabled)**:

- ⚠️ **Warning**: Files are permanently deleted from your server after successful sync
- Significantly reduces disk space usage
- Files are only deleted after verifying they exist in GCS
- Includes safety checks before deletion
- Both original files and all generated sizes are removed

### Deletion from WordPress

- When you delete an attachment from WordPress Media Library
- The plugin automatically deletes the corresponding files from GCS
- This includes the original file and all generated sizes
- Local files are handled according to your Auto-Delete setting

## WP-CLI Commands

The plugin includes WP-CLI commands for managing existing media files:

### Sync All Unsynced Files

Sync all media files that haven't been uploaded to GCS yet (useful for existing sites):

```bash
# Sync all unsynced media files
wp gcs sync

# Sync only the first 100 files
wp gcs sync --limit=100

# Skip the first 50 files and sync the next 100
wp gcs sync --limit=100 --offset=50

# Force re-sync all files (even already synced ones)
wp gcs sync --force
```

### Sync Single Attachment

Sync a specific attachment by ID:

```bash
# Sync attachment with ID 123
wp gcs sync-one 123

# Force re-sync attachment with ID 123 (even if already synced)
wp gcs sync-one 123 --force
```

These commands are perfect for:

- **Initial migration**: Sync existing media when installing the plugin on an existing site
- **Selective sync**: Sync specific files that may have failed during automatic upload
- **Re-sync**: Update files that have changed or need to be re-optimized

## File Structure

```
gcs-media-sync/
├── gcs-sync-plugin.php        # Main plugin file
├── includes/
│   ├── class-gcs-helper.php   # Core GCS functionality
│   ├── class-gcs-admin.php    # Admin interface
│   └── class-gcs-cli.php      # WP-CLI commands
├── uninstall.php              # Plugin cleanup
├── composer.json              # Dependency management
├── install.sh                 # Installation script
└── README.md                  # This file
```

## Configuration Examples

### Basic Configuration

```
Bucket Name: my-wordpress-media
Bucket Folder: uploads
Max Image Width: 1920
Image Quality: 85
```

### Advanced Configuration with Folder Structure

```
Bucket Name: company-assets
Bucket Folder: wordpress/site1/uploads
Max Image Width: 2000
Image Quality: 90
```

## Troubleshooting

### Library Not Found Error

If you see "Google Cloud Storage PHP library is required", install it via Composer:

```bash
composer require google/cloud-storage
```

### Permission Errors

Ensure your service account has the following permissions:

- Storage Object Admin (for the specific bucket)
- Or Storage Admin (for broader access)

### Upload Failures

1. Check WordPress error logs for specific error messages
2. Verify your service account JSON is valid JSON
3. Test bucket access with the GCS Check tool
4. Ensure your bucket exists and is accessible

### Files Not Appearing from GCS

1. Verify files are actually uploaded to GCS (check the bucket)
2. Ensure the `gcs_synced` post meta is set for attachments
3. Check that URLs are being rewritten properly

## Differences from Original Stadshem Implementation

This plugin is adapted from the Stadshem API GCS functionality with these changes:

- **No Task System**: Direct upload instead of queued tasks
- **WordPress Settings**: Uses WordPress options instead of constants
- **Simplified**: Removed estate-specific logic and dependencies
- **Plugin Structure**: Proper WordPress plugin architecture
- **Admin Interface**: WordPress admin pages for configuration

## Security Notes

- The service account JSON is stored in the WordPress database
- Consider using environment variables or file-based credentials for enhanced security
- Ensure your WordPress installation is secure
- Regularly rotate your service account keys

## Contributing

This plugin was extracted from the Stadshem API project. Feel free to submit issues or improvements.

## License

GPL v2 or later
