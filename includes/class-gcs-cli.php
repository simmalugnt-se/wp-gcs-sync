<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP-CLI commands for GCS Media Sync
 */
class GCS_CLI
{
    /**
     * Register WP-CLI commands
     */
    public static function register()
    {
        if (class_exists('WP_CLI')) {
            WP_CLI::add_command('gcs sync', [self::class, 'sync_media']);
            WP_CLI::add_command('gcs sync-one', [self::class, 'sync_single_attachment']);
        }
    }

    /**
     * Sync media files to Google Cloud Storage that haven't been synced yet.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Limit the number of attachments to process.
     * ---
     * default: -1
     * ---
     *
     * [--offset=<number>]
     * : Skip the first n attachments.
     * ---
     * default: 0
     * ---
     *
     * [--force]
     * : Force re-upload of all attachments, even if they've already been synced.
     * ---
     * default: false
     * ---
     *
     * ## EXAMPLES
     *
     *     # Sync all unsynced media files to GCS
     *     $ wp gcs sync
     *
     *     # Sync the first 100 unsynced media files
     *     $ wp gcs sync --limit=100
     *
     *     # Force re-sync all media files
     *     $ wp gcs sync --force
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public static function sync_media($args, $assoc_args)
    {
        // Check if plugin is enabled and configured
        $options = get_option('gcs_sync_options', array());
        if (empty($options['enabled'])) {
            WP_CLI::error('GCS Media Sync is not enabled. Enable it in WordPress admin first.');
            return;
        }

        if (empty($options['bucket_name'])) {
            WP_CLI::error('GCS bucket name is not configured. Configure it in WordPress admin first.');
            return;
        }

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            WP_CLI::error('Google Cloud Storage PHP library not available. Run: composer require google/cloud-storage');
            return;
        }

        // Parse arguments
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : -1;
        $offset = isset($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0;
        $force = isset($assoc_args['force']) && $assoc_args['force'];

        // Get attachments to sync
        $query_args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        );

        // If not forcing re-upload, only get attachments that haven't been synced yet
        if (!$force) {
            $query_args['meta_query'] = array(
                array(
                    'key' => 'gcs_synced',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $query = new WP_Query($query_args);
        $attachment_ids = $query->posts;
        $total = count($attachment_ids);

        if ($total === 0) {
            WP_CLI::success('No media files found that need to be synced to GCS.');
            return;
        }

        WP_CLI::log(sprintf('Found %d media files to sync to GCS.', $total));

        // Create progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Syncing media files to GCS', $total);

        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        foreach ($attachment_ids as $index => $attachment_id) {
            $result = self::sync_attachment_to_gcs($attachment_id, $force);
            
            switch ($result['status']) {
                case 'success':
                    $success_count++;
                    break;
                case 'error':
                    $error_count++;
                    WP_CLI::warning(sprintf('Attachment %d: %s', $attachment_id, $result['message']));
                    break;
                case 'skipped':
                    $skipped_count++;
                    break;
            }

            $progress->tick();

            // Output progress every 10 attachments
            if (($index + 1) % 10 === 0 || $index === $total - 1) {
                WP_CLI::log(sprintf(
                    'Progress: %d/%d (%.1f%%) - Success: %d, Errors: %d, Skipped: %d',
                    $index + 1,
                    $total,
                    ($index + 1) / $total * 100,
                    $success_count,
                    $error_count,
                    $skipped_count
                ));
            }
        }

        $progress->finish();

        WP_CLI::success(sprintf(
            'Finished syncing media files to GCS. Total: %d, Success: %d, Errors: %d, Skipped: %d',
            $total,
            $success_count,
            $error_count,
            $skipped_count
        ));
    }

    /**
     * Sync a single attachment to Google Cloud Storage.
     *
     * ## OPTIONS
     *
     * <attachment-id>
     * : The attachment ID to sync.
     *
     * [--force]
     * : Force upload even if already synced.
     * ---
     * default: false
     * ---
     *
     * ## EXAMPLES
     *
     *     # Sync attachment with ID 123
     *     $ wp gcs sync-one 123
     *
     *     # Force re-sync attachment with ID 123
     *     $ wp gcs sync-one 123 --force
     *
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public static function sync_single_attachment($args, $assoc_args)
    {
        if (empty($args[0])) {
            WP_CLI::error('Please provide an attachment ID.');
            return;
        }

        $attachment_id = (int) $args[0];
        $force = isset($assoc_args['force']) && $assoc_args['force'];

        // Check if plugin is enabled and configured
        $options = get_option('gcs_sync_options', array());
        if (empty($options['enabled'])) {
            WP_CLI::error('GCS Media Sync is not enabled. Enable it in WordPress admin first.');
            return;
        }

        if (empty($options['bucket_name'])) {
            WP_CLI::error('GCS bucket name is not configured. Configure it in WordPress admin first.');
            return;
        }

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            WP_CLI::error('Google Cloud Storage PHP library not available. Run: composer require google/cloud-storage');
            return;
        }

        // Check if attachment exists
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            WP_CLI::error(sprintf('Attachment with ID %d not found.', $attachment_id));
            return;
        }

        WP_CLI::log(sprintf('Syncing attachment %d: %s', $attachment_id, $post->post_title));

        $result = self::sync_attachment_to_gcs($attachment_id, $force);

        switch ($result['status']) {
            case 'success':
                WP_CLI::success(sprintf('Successfully synced attachment %d to GCS.', $attachment_id));
                break;
            case 'error':
                WP_CLI::error(sprintf('Failed to sync attachment %d: %s', $attachment_id, $result['message']));
                break;
            case 'skipped':
                WP_CLI::log(sprintf('Attachment %d was skipped: %s', $attachment_id, $result['message']));
                break;
        }
    }

    /**
     * Sync a single attachment to GCS
     *
     * @param int $attachment_id Attachment ID
     * @param bool $force Force upload even if already synced
     * @return array Result array with status and message
     */
    private static function sync_attachment_to_gcs($attachment_id, $force = false)
    {
        // Check if already synced
        if (!$force && get_post_meta($attachment_id, 'gcs_synced', true)) {
            return array(
                'status' => 'skipped',
                'message' => 'Already synced to GCS'
            );
        }

        // Get the attachment file
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return array(
                'status' => 'error',
                'message' => 'File not found: ' . ($file_path ?: 'unknown')
            );
        }

        try {
            $options = get_option('gcs_sync_options', array());
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);
            $rel_path = str_replace($base_dir, '', $file_path);

            // Initialize GCS client
            $config = array();
            if (!empty($options['service_account_json'])) {
                $json_data = json_decode($options['service_account_json'], true);
                if (is_array($json_data) && isset($json_data['project_id'])) {
                    $config['projectId'] = $json_data['project_id'];
                }
                $temp_file = tempnam(sys_get_temp_dir(), 'gcs_');
                file_put_contents($temp_file, $options['service_account_json']);
                $config['keyFilePath'] = $temp_file;
            }

            $storage = new Google\Cloud\Storage\StorageClient($config);
            $bucket = $storage->bucket($options['bucket_name']);

            // Get GCS path
            $folder = isset($options['bucket_folder']) ? $options['bucket_folder'] : '';
            if (!empty($folder) && substr($folder, -1) !== '/') {
                $folder .= '/';
            }
            $gcs_path = $folder . ltrim($rel_path, '/');

            // Optimize image if needed
            $image_quality = isset($options['image_quality']) ? intval($options['image_quality']) : 85;
            $max_width = isset($options['max_width']) ? intval($options['max_width']) : 1982;
            
            $image_size = @getimagesize($file_path);
            if ($image_size) {
                $image = wp_get_image_editor($file_path);
                if (!is_wp_error($image)) {
                    $size = $image->get_size();
                    if ($size['width'] > $max_width) {
                        $image->resize($max_width, null, false);
                        $image->save($file_path);
                    } else {
                        $image->set_quality($image_quality);
                        $image->save($file_path);
                    }
                }
            }

            // Upload original file
            $content = file_get_contents($file_path);
            if ($content === false) {
                return array(
                    'status' => 'error',
                    'message' => 'Failed to read file content'
                );
            }

            $bucket->upload($content, array(
                'name' => $gcs_path,
                'predefinedAcl' => 'publicRead',
            ));

            $gcs_url = 'https://storage.googleapis.com/' . $options['bucket_name'] . '/' . $gcs_path;
            $gcs_urls = array('full' => $gcs_url);

            // Upload all sizes
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $file_dir = dirname($file_path);
                $rel_dir = dirname($rel_path);

                foreach ($metadata['sizes'] as $size => $size_info) {
                    $size_file_path = $file_dir . '/' . $size_info['file'];
                    if (!file_exists($size_file_path)) {
                        continue;
                    }

                    $size_rel_path = $rel_dir . '/' . $size_info['file'];
                    $size_gcs_path = $folder . ltrim($size_rel_path, '/');
                    $size_content = file_get_contents($size_file_path);

                    if ($size_content !== false) {
                        $bucket->upload($size_content, array(
                            'name' => $size_gcs_path,
                            'predefinedAcl' => 'publicRead',
                        ));
                        $gcs_urls[$size] = 'https://storage.googleapis.com/' . $options['bucket_name'] . '/' . $size_gcs_path;
                    }
                }
            }

            // Update post meta
            update_post_meta($attachment_id, 'gcs_url', $gcs_url);
            update_post_meta($attachment_id, 'gcs_urls', $gcs_urls);
            update_post_meta($attachment_id, 'gcs_synced', true);

            // Clean up temp file
            if (isset($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }

            return array(
                'status' => 'success',
                'message' => 'Synced successfully'
            );

        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
}
