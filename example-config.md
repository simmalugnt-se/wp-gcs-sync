# Example Configuration

## Basic WordPress Site

```
Enable GCS Sync: ✓
Bucket Name: my-site-media
Bucket Folder: wp-uploads
Max Image Width: 1920
Image Quality: 85
Service Account JSON: {
  "type": "service_account",
  "project_id": "my-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "gcs-uploader@my-project.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/gcs-uploader%40my-project.iam.gserviceaccount.com"
}
```

## Multi-site Setup

```
Enable GCS Sync: ✓
Bucket Name: company-assets
Bucket Folder: wordpress/site1/media
Max Image Width: 2000
Image Quality: 90
```

## Development vs Production

### Development
```
Bucket Name: dev-site-media
Bucket Folder: dev/uploads
Max Image Width: 1500
Image Quality: 75
```

### Production
```
Bucket Name: prod-site-media
Bucket Folder: uploads
Max Image Width: 2000
Image Quality: 90
```

## Commands for Migration

After installing the plugin on an existing site:

```bash
# First, sync existing media
wp gcs sync

# Check specific attachment if needed
wp gcs sync-one 123

# Re-sync all if needed
wp gcs sync --force
```
