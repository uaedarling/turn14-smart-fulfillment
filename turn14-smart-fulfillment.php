<?php
/**
 * Plugin Name: Turn14 Smart Fulfillment
 * Plugin URI: https://github.com/uaedarling/turn14-smart-fulfillment
 * Description: Intelligent stock, pricing, and shipping management for Turn14 integration. Automatically routes orders between warehouse and Turn14 based on stock levels.
 * Version: 1.0.0
 * Author: Transformer GT
 * Author URI: https://transformergt.com
 * License: GPL v2 or later
 * Text Domain: turn14-smart-fulfillment
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('T14SF_VERSION', '1.0.0');
define('T14SF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('T14SF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('T14SF_PLUGIN_FILE', __FILE__);
define('T14SF_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Turn14_Smart_Fulfillment {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>Turn14 Smart Fulfillment</strong> requires WooCommerce to be installed and activated.</p></div>';
    }

    private function load_dependencies() {
        // Core Logic
        require_once T14SF_PLUGIN_DIR . 'includes/price-manager.php';
        require_once T14SF_PLUGIN_DIR . 'includes/stock-manager.php';
        require_once T14SF_PLUGIN_DIR . 'includes/shipping-splitter.php';
        require_once T14SF_PLUGIN_DIR . 'includes/order-tagging.php';
        
        // API & Shipping
        require_once T14SF_PLUGIN_DIR . 'includes/turn14-api.php';
        require_once T14SF_PLUGIN_DIR . 'includes/turn14-shipping-method.php';

        // Admin
        if (is_admin()) {
            require_once T14SF_PLUGIN_DIR . 'admin/settings-page.php';
            require_once T14SF_PLUGIN_DIR . 'admin/product-meta-box.php';
        }
    }

    private function init_hooks() {
        register_activation_hook(T14SF_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(T14SF_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . T14SF_PLUGIN_BASENAME, array($this, 'add_settings_link'));

        // Register shipping method with WooCommerce
        add_action('woocommerce_shipping_init', array($this, 'register_turn14_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_turn14_shipping_method'));
    }

    public function register_turn14_shipping_method() {
        if (!class_exists('T14SF_Turn14_Shipping')) {
            require_once T14SF_PLUGIN_DIR . 'includes/turn14-shipping-method.php';
        }
    }

    public function add_turn14_shipping_method($methods) {
        $methods['turn14_shipping'] = 'T14SF_Turn14_Shipping';
        return $methods;
    }

    public function activate() {
        if (false === get_option('t14sf_activated')) {
            add_option('t14sf_activated', current_time('mysql'));
            add_option('t14sf_version', T14SF_VERSION);
            
            // Core Defaults
            add_option('t14sf_price_mode', 'auto');
            add_option('t14sf_stock_threshold', 0);
            add_option('t14sf_turn14_method_id', 'turn14_shipping');
            add_option('t14sf_local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
            
            // Turn14 API Defaults
            add_option('t14sf_turn14_api_endpoint', '');
            add_option('t14sf_turn14_api_key', '');
            add_option('t14sf_turn14_markup_percent', 0);
        }
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function add_admin_menu() {
        add_menu_page(
            'Turn14 Fulfillment',
            'Turn14 Fulfillment',
            'manage_options',
            't14sf-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            't14sf-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            't14sf-dashboard',
            array($this, 'render_dashboard')
        );
    }

    public function render_dashboard() {
        include T14SF_PLUGIN_DIR . 'admin/settings-page.php';
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 't14sf') !== false) {
            // Note: Ensure admin.css exists in assets/ folder if you want styling
            wp_enqueue_style('t14sf-admin', T14SF_PLUGIN_URL . 'assets/admin.css', array(), T14SF_VERSION);
        }
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=t14sf-dashboard') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function get_option($key, $default = '') {
        return get_option('t14sf_' . $key, $default);
    }

    public static function update_option($key, $value) {
        return update_option('t14sf_' . $key, $value);
    }
}

function turn14_smart_fulfillment_init() {
    return Turn14_Smart_Fulfillment::get_instance();
}
add_action('plugins_loaded', 'turn14_smart_fulfillment_init');
