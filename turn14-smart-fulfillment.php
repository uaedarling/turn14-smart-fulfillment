<?php
/**
 * Plugin Name: Turn14 Smart Fulfillment
 * Plugin URI: https://github.com/uaedarling/turn14-smart-fulfillment
 * Description: Intelligent stock, pricing, and shipping management for Turn14 integration with WooCommerce.
 * Version: 1.0.0
 * Author: UAE Darling
 * Author URI: https://github.com/uaedarling
 * Text Domain: turn14-smart-fulfillment
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('T14SF_VERSION', '1.0.0');
define('T14SF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('T14SF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('T14SF_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Turn14_Smart_Fulfillment {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 10);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Turn14 Smart Fulfillment', 'turn14-smart-fulfillment'); ?></strong>
                <?php esc_html_e('requires WooCommerce to be installed and activated. Please install WooCommerce first.', 'turn14-smart-fulfillment'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core non-admin includes
        $core_files = array(
            'includes/turn14-api-config.php',
            'includes/price-manager.php',
            'includes/stock-manager.php',
            'includes/shipping-splitter.php',
            'includes/order-tagging.php',
            'includes/turn14-api.php',
            'includes/turn14-shipping-method.php',
        );

        foreach ($core_files as $relative) {
            $path = T14SF_PLUGIN_DIR . $relative;
            if (file_exists($path)) {
                require_once $path;
            } else {
                error_log('Turn14 Smart Fulfillment missing file: ' . $path);
                add_action('admin_notices', function() use ($relative) {
                    printf(
                        '<div class="notice notice-warning"><p><strong>Turn14 Smart Fulfillment:</strong> Missing file: %s. Please re-upload the plugin files.</p></div>',
                        esc_html($relative)
                    );
                });
            }
        }

        // Do NOT include admin files here. They will be included only when rendering the admin page.
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . T14SF_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // AJAX handlers
        add_action('wp_ajax_t14sf_test_api_connection', array($this, 'ajax_test_api_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Turn14 Fulfillment', 'turn14-smart-fulfillment'),
            __('Turn14 Fulfillment', 'turn14-smart-fulfillment'),
            'manage_options',
            't14sf-dashboard',
            array($this, 'render_settings_page'),
            'dashicons-store',
            56
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            't14sf-dashboard',
            __('Dashboard', 'turn14-smart-fulfillment'),
            __('Dashboard', 'turn14-smart-fulfillment'),
            'manage_options',
            't14sf-dashboard',
            array($this, 'render_settings_page')
        );
        
        // System Check submenu
        add_submenu_page(
            't14sf-dashboard',
            __('System Check', 'turn14-smart-fulfillment'),
            __('System Check', 'turn14-smart-fulfillment'),
            'manage_options',
            't14sf-system-check',
            array($this, 'render_system_check_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings_file = T14SF_PLUGIN_DIR . 'admin/settings-page.php';
        if (file_exists($settings_file)) {
            include $settings_file;
        }
    }
    
    /**
     * Render system check page
     */
    public function render_system_check_page() {
        $system_check_file = T14SF_PLUGIN_DIR . 'admin/system-check.php';
        if (file_exists($system_check_file)) {
            include $system_check_file;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 't14sf-') === false) {
            return;
        }
        
        wp_enqueue_style(
            't14sf-admin-styles',
            T14SF_PLUGIN_URL . 'assets/admin.css',
            array(),
            T14SF_VERSION
        );
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=t14sf-dashboard'),
            __('Settings', 'turn14-smart-fulfillment')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api_connection() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Verify nonce
        check_ajax_referer('t14sf_test_api_connection', 'nonce');
        
        // Test the connection
        $result = Turn14_API_Config::test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get option value
     * 
     * @param string $key Option key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    public static function get_option($key, $default = false) {
        $option_name = 't14sf_' . $key;
        return get_option($option_name, $default);
    }
    
    /**
     * Update option value
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @return bool Whether the option was updated
     */
    public static function update_option($key, $value) {
        $option_name = 't14sf_' . $key;
        return update_option($option_name, $value);
    }
    
    /**
     * Delete option
     * 
     * @param string $key Option key
     * @return bool Whether the option was deleted
     */
    public static function delete_option($key) {
        $option_name = 't14sf_' . $key;
        return delete_option($option_name);
    }
}

/**
 * Plugin activation hook
 */
function t14sf_activate() {
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.8', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Turn14 Smart Fulfillment requires WordPress 5.8 or higher.', 'turn14-smart-fulfillment'),
            __('Plugin Activation Error', 'turn14-smart-fulfillment'),
            array('back_link' => true)
        );
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Turn14 Smart Fulfillment requires PHP 7.4 or higher.', 'turn14-smart-fulfillment'),
            __('Plugin Activation Error', 'turn14-smart-fulfillment'),
            array('back_link' => true)
        );
    }
    
    // Set default API options (only if not already set)
    add_option('t14sf_api_client_id', 'c387a85cac64c9c4fa6d57a5ef0dfb8ad97a6d26');
    add_option('t14sf_api_client_secret', '15469b8ad5adf0c1f9d48faa969dc5a6d6e59e9e');
    add_option('t14sf_api_base_url', 'https://apitest.turn14.com');
    add_option('t14sf_api_currency_rate', 3.699);
    
    // Set flag to show system check notice
    set_transient('t14sf_activation_redirect', true, 30);
}
register_activation_hook(__FILE__, 't14sf_activate');

/**
 * Plugin deactivation hook
 */
function t14sf_deactivate() {
    // Clean up transients
    delete_transient('t14sf_activation_redirect');
}
register_deactivation_hook(__FILE__, 't14sf_deactivate');

/**
 * Redirect to system check on activation
 */
function t14sf_activation_redirect() {
    if (get_transient('t14sf_activation_redirect')) {
        delete_transient('t14sf_activation_redirect');
        
        // Only redirect if not in bulk activation
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=t14sf-system-check'));
            exit;
        }
    }
}
add_action('admin_init', 't14sf_activation_redirect');

/**
 * Initialize the plugin
 */
function turn14_smart_fulfillment() {
    return Turn14_Smart_Fulfillment::get_instance();
}

// Start the plugin
turn14_smart_fulfillment();
