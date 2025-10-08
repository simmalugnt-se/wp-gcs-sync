<?php

/**
 * Plugin Name: GCS Media Sync
 * Plugin URI: https://github.com/your-username/gcs-media-sync
 * Description: Automatically sync WordPress media uploads to Google Cloud Storage with optional local file deletion.
 * Version: 1.0.0
 * Author: Jens L Waern
 * Author URI: https://simmalugnt.se
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gcs-sync
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GCS_SYNC_VERSION', '1.0.0');
define('GCS_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GCS_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GCS_SYNC_PLUGIN_FILE', __FILE__);

// Load Composer autoloader if it exists
if (file_exists(GCS_SYNC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once GCS_SYNC_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main plugin class
 */
class GCS_Sync_Plugin
{

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init()
    {
        // Load required files
        $this->load_dependencies();

        // Initialize hooks
        add_action('init', array($this, 'init_hooks'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        require_once GCS_SYNC_PLUGIN_DIR . 'includes/class-gcs-helper.php';
        require_once GCS_SYNC_PLUGIN_DIR . 'includes/class-gcs-admin.php';
        require_once GCS_SYNC_PLUGIN_DIR . 'includes/class-gcs-cli.php';
    }

    /**
     * Initialize hooks
     */
    public function init_hooks()
    {
        // Initialize GCS functionality if enabled and configured
        if ($this->is_gcs_enabled() && $this->is_gcs_configured()) {
            GCS_Helper::init();
        }

        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            GCS_CLI::register();
        }
    }

    /**
     * Admin menu
     */
    public function admin_menu()
    {
        add_options_page(
            __('GCS Media Sync', 'gcs-sync'),
            __('GCS Media Sync', 'gcs-sync'),
            'manage_options',
            'gcs-sync',
            array('GCS_Admin', 'settings_page')
        );

        // Add tools submenu for diagnostics
        add_submenu_page(
            'tools.php',
            __('GCS Sync Check', 'gcs-sync'),
            __('GCS Check', 'gcs-sync'),
            'manage_options',
            'gcs-sync-check',
            array('GCS_Admin', 'check_page')
        );
    }

    /**
     * Admin init
     */
    public function admin_init()
    {
        // Register settings
        register_setting('gcs_sync_settings', 'gcs_sync_options', array(
            'sanitize_callback' => array('GCS_Admin', 'sanitize_settings'),
        ));

        // Add attachment fields for GCS status and check button
        add_filter('attachment_fields_to_edit', array('GCS_Admin', 'add_attachment_fields'), 10, 2);

        // Enqueue scripts for attachment pages
        add_action('admin_enqueue_scripts', array('GCS_Admin', 'enqueue_attachment_scripts'));

        // Register AJAX handler for checking existing files on GCS
        add_action('wp_ajax_gcs_check_existing_file', array('GCS_Admin', 'ajax_check_existing_file'));
    }

    /**
     * Admin notices
     */
    public function admin_notices()
    {
        // Check if Google Cloud Storage library is available
        if (!class_exists('Google\Cloud\Storage\StorageClient')) {
            echo '<div class="notice notice-error"><p>';
            echo __('GCS Media Sync: Google Cloud Storage PHP library is required.', 'gcs-sync');
            echo '<br><strong>Solution:</strong> ';
            if (file_exists(GCS_SYNC_PLUGIN_DIR . 'composer.json')) {
                echo __('Run <code>composer install</code> in the plugin directory, or install globally with <code>composer require google/cloud-storage</code>', 'gcs-sync');
            } else {
                echo __('Install it using Composer: <code>composer require google/cloud-storage</code>', 'gcs-sync');
            }
            echo '</p></div>';
        }

        // Check if plugin is enabled but not configured
        if ($this->is_gcs_enabled() && !$this->is_gcs_configured()) {
            $settings_url = admin_url('options-general.php?page=gcs-sync');
            echo '<div class="notice notice-warning"><p>';
            printf(__('GCS Media Sync is enabled but not configured. <a href="%s">Configure it here</a>.', 'gcs-sync'), $settings_url);
            echo '</p></div>';
        }
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create default options
        $default_options = array(
            'enabled' => false,
            'bucket_name' => '',
            'bucket_folder' => '',
            'service_account_json' => '',
            'auto_delete_local' => false,
            'image_quality' => 85,
            'max_width' => 1920,
        );

        add_option('gcs_sync_options', $default_options);

        // Set activation flag for notices
        set_transient('gcs_sync_activated', true, 30);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up transients
        delete_transient('gcs_sync_activated');
    }

    /**
     * Check if GCS is enabled
     */
    private function is_gcs_enabled()
    {
        $options = get_option('gcs_sync_options', array());
        return !empty($options['enabled']);
    }

    /**
     * Check if GCS is configured
     */
    private function is_gcs_configured()
    {
        $options = get_option('gcs_sync_options', array());
        return !empty($options['bucket_name']);
    }
}

/**
 * Initialize the plugin
 */
function gcs_sync_init()
{
    return GCS_Sync_Plugin::instance();
}

// Initialize the plugin
gcs_sync_init();
