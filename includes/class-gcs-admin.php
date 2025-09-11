<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GCS_Admin
{
    public static function settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $options = get_option('gcs_sync_options', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('GCS Media Sync Settings', 'gcs-sync'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('gcs_sync_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable GCS Sync', 'gcs-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="gcs_sync_options[enabled]" value="1" <?php checked(!empty($options['enabled'])); ?> />
                                <?php esc_html_e('Enable syncing uploads to Google Cloud Storage', 'gcs-sync'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Bucket Name', 'gcs-sync'); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="gcs_sync_options[bucket_name]" value="<?php echo esc_attr($options['bucket_name'] ?? ''); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Bucket Folder (optional)', 'gcs-sync'); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="gcs_sync_options[bucket_folder]" value="<?php echo esc_attr($options['bucket_folder'] ?? ''); ?>" />
                            <p class="description"><?php esc_html_e('Prefix inside the bucket to store files, e.g., site-uploads', 'gcs-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Service Account JSON', 'gcs-sync'); ?></th>
                        <td>
                            <textarea name="gcs_sync_options[service_account_json]" rows="8" class="large-text" placeholder='{ "type": "service_account", ... }'><?php echo esc_textarea($options['service_account_json'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Paste the JSON of a Google Cloud service account with access to the bucket. Stored in the database.', 'gcs-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Max Image Width', 'gcs-sync'); ?></th>
                        <td>
                            <input type="number" min="320" max="4000" name="gcs_sync_options[max_width]" value="<?php echo esc_attr($options['max_width'] ?? 1982); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Image Quality', 'gcs-sync'); ?></th>
                        <td>
                            <input type="number" min="10" max="100" name="gcs_sync_options[image_quality]" value="<?php echo esc_attr($options['image_quality'] ?? 85); ?>" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function sanitize_settings($input)
    {
        $output = array();
        $output['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $output['bucket_name'] = isset($input['bucket_name']) ? sanitize_text_field($input['bucket_name']) : '';
        $output['bucket_folder'] = isset($input['bucket_folder']) ? sanitize_text_field($input['bucket_folder']) : '';
        $output['service_account_json'] = isset($input['service_account_json']) ? wp_kses_post($input['service_account_json']) : '';
        $output['max_width'] = isset($input['max_width']) ? max(320, min(4000, intval($input['max_width']))) : 1982;
        $output['image_quality'] = isset($input['image_quality']) ? max(10, min(100, intval($input['image_quality']))) : 85;
        return $output;
    }

    public static function check_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('gcs_sync_options', array());
        $library = class_exists('Google\\Cloud\\Storage\\StorageClient');
        $autoloader_path = GCS_SYNC_PLUGIN_DIR . 'vendor/autoload.php';
        $autoloader_exists = file_exists($autoloader_path);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('GCS Media Sync Check', 'gcs-sync'); ?></h1>
            <pre>
<strong>=== LIBRARY STATUS ===</strong>
<?php echo $library ? '✅ Google Cloud Storage library is loaded' : '❌ Google Cloud Storage library is NOT loaded'; ?>
<?php echo $autoloader_exists ? '✅ Composer autoloader found at: vendor/autoload.php' : '❌ Composer autoloader NOT found'; ?>

<strong>=== PLUGIN CONFIGURATION ===</strong>
Enabled:    <?php echo !empty($options['enabled']) ? '✅ true' : '❌ false'; ?>
Bucket:     <?php echo !empty($options['bucket_name']) ? '✅ ' . esc_html($options['bucket_name']) : '❌ (not set)'; ?>
Folder:     <?php echo esc_html($options['bucket_folder'] ?? '(root)'); ?>
Max width:  <?php echo esc_html($options['max_width'] ?? '1982'); ?>px
Quality:    <?php echo esc_html($options['image_quality'] ?? '85'); ?>%
Service Account: <?php echo !empty($options['service_account_json']) ? '✅ configured' : '❌ not configured'; ?>

<strong>=== TROUBLESHOOTING ===</strong>
<?php if (!$autoloader_exists): ?>
⚠️  Run 'composer install' in the plugin directory to install dependencies.
<?php endif; ?>
<?php if (!$library && $autoloader_exists): ?>
⚠️  Autoloader exists but library not loaded. Check PHP error logs.
<?php endif; ?>
<?php if (!empty($options['enabled']) && empty($options['bucket_name'])): ?>
⚠️  Plugin is enabled but bucket name is missing.
<?php endif; ?>

Plugin path: <?php echo esc_html(GCS_SYNC_PLUGIN_DIR); ?>
            </pre>
        </div>
        <?php
    }
}

