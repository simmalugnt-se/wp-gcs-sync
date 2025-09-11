<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class GCS_Helper
{
    private static $bucket_name;
    private static $bucket_folder;
    private static $service_account_json;
    private static $is_enabled = false;
    private static $filters_added = false;
    private static $storage_client = null;
    private static $image_quality = 85;
    private static $max_width = 1982;
    private static $auto_delete_local = false;

    public static function init()
    {
        $options = get_option('gcs_sync_options', array());
        self::$is_enabled = !empty($options['enabled']);

        if (!self::$is_enabled) {
            return;
        }

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            error_log('GCS Media Sync: Google Cloud Storage library not found. Install google/cloud-storage via Composer.');
            self::$is_enabled = false;
            return;
        }

        self::$bucket_name = isset($options['bucket_name']) ? $options['bucket_name'] : '';
        self::$bucket_folder = isset($options['bucket_folder']) ? $options['bucket_folder'] : '';
        self::$service_account_json = isset($options['service_account_json']) ? $options['service_account_json'] : '';
        self::$image_quality = isset($options['image_quality']) ? intval($options['image_quality']) : 85;
        self::$max_width = isset($options['max_width']) ? intval($options['max_width']) : 1982;
        self::$auto_delete_local = isset($options['auto_delete_local']) ? !empty($options['auto_delete_local']) : false;

        if (empty(self::$bucket_name)) {
            error_log('GCS Media Sync: Bucket name is not configured.');
            self::$is_enabled = false;
            return;
        }

        if (!self::$filters_added) {
            add_filter('wp_handle_upload', [self::class, 'handle_upload'], 10, 2);
            add_filter('wp_delete_file', [self::class, 'prevent_local_file_deletion'], 10, 1);
            add_filter('wp_get_attachment_url', [self::class, 'modify_attachment_url'], 10, 2);
            add_filter('wp_get_attachment_image_src', [self::class, 'modify_attachment_image_src'], 10, 4);
            add_filter('wp_calculate_image_srcset', [self::class, 'modify_image_srcset'], 10, 5);
            add_action('delete_attachment', [self::class, 'delete_from_gcs'], 10, 1);
            self::$filters_added = true;
        }
    }

    private static function get_project_id()
    {
        if (empty(self::$service_account_json)) {
            return '';
        }
        try {
            $json_data = json_decode(self::$service_account_json, true);
            if (is_array($json_data) && isset($json_data['project_id'])) {
                return $json_data['project_id'];
            }
        } catch (Exception $e) {
            error_log('GCS Media Sync: Failed to parse Service Account JSON: ' . $e->getMessage());
        }
        return '';
    }

    private static function get_gcs_path($file_name)
    {
        $folder = self::$bucket_folder;
        if (!empty($folder) && substr($folder, -1) !== '/') {
            $folder .= '/';
        }
        return $folder . ltrim($file_name, '/');
    }

    public static function modify_attachment_url($url, $attachment_id)
    {
        if (!self::$is_enabled) {
            return $url;
        }
        $is_synced = get_post_meta($attachment_id, 'gcs_synced', true);
        if (!$is_synced) {
            return $url;
        }

        $gcs_url = get_post_meta($attachment_id, 'gcs_url', true);
        if (!empty($gcs_url)) {
            $upload_dir = wp_upload_dir();
            $wp_base_url = $upload_dir['baseurl'];
            $path_parts = pathinfo($url);
            $filename = $path_parts['basename'];
            $original_filename = basename(get_attached_file($attachment_id));

            if ($filename !== $original_filename) {
                // This is likely a resized image, check if we have a specific GCS URL for it
                $gcs_urls = get_post_meta($attachment_id, 'gcs_urls', true);
                if (!empty($gcs_urls) && is_array($gcs_urls)) {
                    // Look for the size in gcs_urls array
                    foreach ($gcs_urls as $size => $size_url) {
                        if ($size !== 'full' && strpos($filename, $size) !== false) {
                            return $size_url;
                        }
                    }

                    // If no specific size found, try to match by filename
                    $metadata = wp_get_attachment_metadata($attachment_id);
                    if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $size => $size_info) {
                            if ($size_info['file'] === $filename && isset($gcs_urls[$size])) {
                                return $gcs_urls[$size];
                            }
                        }
                    }
                }

                // Fallback to string replacement if no specific URL found
                return str_replace($wp_base_url, $gcs_url, $url);
            }
            return $gcs_url;
        }
        return $url;
    }

    public static function modify_attachment_image_src($image, $attachment_id, $size, $icon)
    {
        if (!self::$is_enabled || !is_array($image)) {
            return $image;
        }
        $image[0] = self::modify_attachment_url($image[0], $attachment_id);
        return $image;
    }

    public static function modify_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!self::$is_enabled || empty($sources)) {
            return $sources;
        }
        foreach ($sources as &$source) {
            $source['url'] = self::modify_attachment_url($source['url'], $attachment_id);
        }
        return $sources;
    }

    public static function handle_upload($upload, $context)
    {
        if (!self::$is_enabled) {
            return $upload;
        }

        // Upload original and sizes after WordPress generates sizes (handled by attachment metadata filter)
        add_filter('wp_generate_attachment_metadata', function ($metadata, $attachment_id) use ($upload) {
            try {
                self::upload_attachment_and_sizes_to_gcs($attachment_id, $upload['file']);
            } catch (Exception $e) {
                error_log('GCS Media Sync: Upload error: ' . $e->getMessage());
            }
            return $metadata;
        }, 10, 2);

        return $upload;
    }

    private static function get_storage_client()
    {
        if (self::$storage_client === null) {
            $config = [];
            $project_id = self::get_project_id();
            if (!empty($project_id)) {
                $config['projectId'] = $project_id;
            }
            if (!empty(self::$service_account_json)) {
                $temp_file = tempnam(sys_get_temp_dir(), 'gcs_');
                file_put_contents($temp_file, self::$service_account_json);
                $config['keyFilePath'] = $temp_file;
            }
            self::$storage_client = new Google\Cloud\Storage\StorageClient($config);
        }
        return self::$storage_client;
    }

    private static function close_storage_client()
    {
        if (self::$storage_client !== null) {
            self::$storage_client = null;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    private static function upload_attachment_and_sizes_to_gcs($attachment_id, $file_path)
    {
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']);
        $rel_path = str_replace($base_dir, '', $file_path);
        $file_dir = dirname($file_path);
        $rel_dir = dirname($rel_path);

        // Optimize original image if possible
        $image_size = @getimagesize($file_path);
        if ($image_size) {
            $image = wp_get_image_editor($file_path);
            if (!is_wp_error($image)) {
                $size = $image->get_size();
                if ($size['width'] > self::$max_width) {
                    $image->resize(self::$max_width, null, false);
                } else {
                    $image->set_quality(self::$image_quality);
                }
                $image->save($file_path);
            }
        }

        $storage = self::get_storage_client();
        $bucket = $storage->bucket(self::$bucket_name);

        // Upload original
        $gcs_path = self::get_gcs_path($rel_path);
        $content = file_get_contents($file_path);
        if ($content !== false) {
            $bucket->upload($content, [
                'name' => $gcs_path,
                'predefinedAcl' => 'publicRead',
            ]);
        }
        $gcs_url = 'https://storage.googleapis.com/' . self::$bucket_name . '/' . $gcs_path;

        // Upload sizes
        $metadata = wp_get_attachment_metadata($attachment_id);
        $gcs_urls = array('full' => $gcs_url);
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_info) {
                $size_file_path = $file_dir . '/' . $size_info['file'];
                if (!file_exists($size_file_path)) {
                    continue;
                }
                $size_rel_path = $rel_dir . '/' . $size_info['file'];
                $size_gcs_path = self::get_gcs_path($size_rel_path);
                $size_content = file_get_contents($size_file_path);
                if ($size_content !== false) {
                    $bucket->upload($size_content, [
                        'name' => $size_gcs_path,
                        'predefinedAcl' => 'publicRead',
                    ]);
                    $gcs_urls[$size] = 'https://storage.googleapis.com/' . self::$bucket_name . '/' . $size_gcs_path;
                }
            }
        }

        update_post_meta($attachment_id, 'gcs_url', $gcs_url);
        update_post_meta($attachment_id, 'gcs_urls', $gcs_urls);
        update_post_meta($attachment_id, 'gcs_synced', true);

        // Delete local files if auto-delete is enabled and upload was successful
        if (self::$auto_delete_local) {
            self::delete_local_files_after_sync($attachment_id, $file_path, $metadata);
        }

        self::close_storage_client();
    }

    /**
     * Delete local files after successful sync to GCS
     * Includes safety checks to verify files exist in GCS before deletion
     */
    private static function delete_local_files_after_sync($attachment_id, $file_path, $metadata)
    {
        try {
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);
            $rel_path = str_replace($base_dir, '', $file_path);
            $file_dir = dirname($file_path);
            $rel_dir = dirname($rel_path);

            $storage = self::get_storage_client();
            $bucket = $storage->bucket(self::$bucket_name);

            // Verify original file exists in GCS before deleting locally
            $gcs_path = self::get_gcs_path($rel_path);
            $object = $bucket->object($gcs_path);

            if ($object->exists() && file_exists($file_path)) {
                if (unlink($file_path)) {
                    error_log("GCS Media Sync: Deleted local file after sync: {$file_path}");
                } else {
                    error_log("GCS Media Sync: Failed to delete local file: {$file_path}");
                }
            } else {
                error_log("GCS Media Sync: Skipping local file deletion - file not confirmed in GCS: {$gcs_path}");
            }

            // Delete size files if they exist in GCS
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $size_file_path = $file_dir . '/' . $size_info['file'];
                    $size_rel_path = $rel_dir . '/' . $size_info['file'];
                    $size_gcs_path = self::get_gcs_path($size_rel_path);
                    $size_object = $bucket->object($size_gcs_path);

                    if ($size_object->exists() && file_exists($size_file_path)) {
                        if (unlink($size_file_path)) {
                            error_log("GCS Media Sync: Deleted local size file after sync: {$size_file_path}");
                        } else {
                            error_log("GCS Media Sync: Failed to delete local size file: {$size_file_path}");
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('GCS Media Sync: Error during local file deletion: ' . $e->getMessage());
        } finally {
            self::close_storage_client();
        }
    }

    public static function prevent_local_file_deletion($file_path)
    {
        if (!self::$is_enabled) {
            return $file_path;
        }

        // If auto-delete is enabled, allow WordPress to delete files normally
        // since we handle deletion ourselves after upload
        if (self::$auto_delete_local) {
            return $file_path;
        }

        // Keep the file locally by returning null (so WP doesn't delete it)
        return null;
    }

    public static function delete_from_gcs($attachment_id)
    {
        if (!self::$is_enabled) {
            return;
        }

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            error_log('GCS Media Sync: Google Cloud Storage PHP library not available');
            return;
        }

        try {
            $file = get_attached_file($attachment_id);
            if (!$file) {
                return;
            }

            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);
            $rel_path = str_replace($base_dir, '', $file);

            $storage = self::get_storage_client();
            $bucket = $storage->bucket(self::$bucket_name);

            // Original
            $gcs_path = self::get_gcs_path($rel_path);
            $object = $bucket->object($gcs_path);
            if ($object->exists()) {
                $object->delete();
            }

            // Sizes
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $dir = dirname($rel_path);
                foreach ($metadata['sizes'] as $size_info) {
                    $size_path = $dir . '/' . $size_info['file'];
                    $size_gcs_path = self::get_gcs_path($size_path);
                    $size_object = $bucket->object($size_gcs_path);
                    if ($size_object->exists()) {
                        $size_object->delete();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('GCS Media Sync: deletion error: ' . $e->getMessage());
        } finally {
            self::close_storage_client();
        }
    }
}
