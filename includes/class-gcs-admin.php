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
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Delete Local Files', 'gcs-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="gcs_sync_options[auto_delete_local]" value="1" <?php checked(!empty($options['auto_delete_local'])); ?> />
                                <?php esc_html_e('Delete files from server after successful sync to GCS (saves disk space)', 'gcs-sync'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Warning: Files will be permanently removed from your server. Make sure your GCS bucket is properly configured and accessible.', 'gcs-sync'); ?></p>
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
        $output['auto_delete_local'] = !empty($input['auto_delete_local']) ? 1 : 0;
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
Auto-delete: <?php echo !empty($options['auto_delete_local']) ? '⚠️  enabled (files will be deleted from server)' : '✅ disabled (files kept on server)'; ?>
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

    /**
     * Add GCS check button to attachment edit page
     */
    public static function add_attachment_fields($form_fields, $post)
    {
        $is_synced = get_post_meta($post->ID, 'gcs_synced', true);
        $gcs_url = get_post_meta($post->ID, 'gcs_url', true);

        $status_text = $is_synced ? 'Yes' : 'No';
        $status_color = $is_synced ? '#46b450' : '#dc3232';

        $html = '<div style="margin: 10px 0;">';
        $html .= '<p><strong>GCS Status:</strong> <span style="color: ' . $status_color . ';">' . esc_html($status_text) . '</span></p>';

        if ($is_synced && $gcs_url) {
            $html .= '<p><strong>GCS URL:</strong><br><a href="' . esc_url($gcs_url) . '" target="_blank" style="word-break: break-all; font-size: 12px;">' . esc_html($gcs_url) . '</a></p>';
        }

        $html .= '<p>';
        $html .= '<button type="button" class="button button-secondary" id="gcs-check-existing" data-attachment-id="' . esc_attr($post->ID) . '">';
        $html .= 'Check if File Exists on GCS';
        $html .= '</button>';
        $html .= '<span id="gcs-check-status" style="margin-left: 10px;"></span>';
        $html .= '</p>';
        $html .= '</div>';

        $form_fields['gcs_sync'] = array(
            'label' => 'GCS Sync',
            'input' => 'html',
            'html' => $html,
        );

        return $form_fields;
    }

    /**
     * Enqueue admin scripts for attachment page
     */
    public static function enqueue_attachment_scripts($hook)
    {
        // Only load on post edit page or media upload page
        if (!in_array($hook, array('post.php', 'upload.php', 'media.php'))) {
            return;
        }

        // Inline script since it's small
        add_action('admin_footer', array(__CLASS__, 'output_attachment_script'));
    }

    /**
     * Output inline JavaScript for GCS check button
     */
    public static function output_attachment_script()
    {
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle GCS check button click
                $(document).on('click', '#gcs-check-existing', function(e) {
                    e.preventDefault();

                    var button = $(this);
                    var attachmentId = button.data('attachment-id');
                    var statusSpan = $('#gcs-check-status');

                    // Disable button and show loading
                    button.prop('disabled', true);
                    statusSpan.html('<span style="color: #0073aa;">Checking GCS...</span>');

                    // Make AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gcs_check_existing_file',
                            attachment_id: attachmentId,
                            nonce: '<?php echo wp_create_nonce('gcs_check_existing'); ?>'
                        },
                        success: function(response) {
                            button.prop('disabled', false);

                            if (response.success) {
                                statusSpan.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');

                                // Refresh the page after 2 seconds to show updated status
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                statusSpan.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            button.prop('disabled', false);
                            statusSpan.html('<span style="color: #dc3232;">Error: ' + error + '</span>');
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * AJAX handler to check if file exists on GCS
     */
    public static function ajax_check_existing_file()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gcs_check_existing')) {
            wp_send_json_error(array('message' => 'Invalid security token.'));
            return;
        }

        // Check user permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
            return;
        }

        // Get attachment ID
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error(array('message' => 'Invalid attachment ID.'));
            return;
        }

        // Check if attachment exists
        if (get_post_type($attachment_id) !== 'attachment') {
            wp_send_json_error(array('message' => 'Attachment not found.'));
            return;
        }

        // Call the helper function to check GCS and update meta
        $result = GCS_Helper::check_and_update_gcs_status($attachment_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
