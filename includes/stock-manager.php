<?php
/**
 * Stock Manager
 * Handles stock display logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Stock_Manager {
    
    public function __construct() {
        // Override WooCommerce stock check to show combined stock
        add_filter('woocommerce_product_get_stock_quantity', array($this, 'get_combined_stock'), 10, 2);
        add_filter('woocommerce_product_variation_get_stock_quantity', array($this, 'get_combined_stock'), 10, 2);
        add_filter('woocommerce_product_is_in_stock', array($this, 'check_combined_stock'), 10, 2);
        add_filter('woocommerce_product_get_stock_status', array($this, 'get_stock_status'), 10, 2);
        
        // Display stock information on product page
        add_action('woocommerce_product_meta_start', array($this, 'display_stock_info'));
    }
    
    /**
     * Get combined stock (local + Turn14)
     */
    public function get_combined_stock($stock, $product) {
        $product_id = $product->get_id();
        
        // Get local stock
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        
        // Get Turn14 stock
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        // Return combined total
        return $local_stock + $turn14_stock;
    }
    
    /**
     * Check if product is in stock (either local OR Turn14)
     */
    public function check_combined_stock($in_stock, $product) {
        $product_id = $product->get_id();
        
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        // Product is in stock if EITHER source has stock
        return ($local_stock > 0 || $turn14_stock > 0);
    }
    
    public function get_stock_status($status, $product) {
        if ($this->check_combined_stock(true, $product)) {
            return 'instock';
        }
        return 'outofstock';
    }
    
    /**
     * Display stock information on product page
     */
    public function display_stock_info() {
        global $product;
        if (!$product) return;
        
        $product_id = $product->get_id();
        
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        if ($local_stock > 0 || $turn14_stock > 0) {
            echo '<div class="t14sf-stock-info" style="margin: 10px 0; padding: 10px; background: #f0f0f0; border-radius: 4px;">';
            
            if ($local_stock > 0) {
                echo '<p style="margin: 5px 0;"><strong>🏭 Local Warehouse:</strong> ' . esc_html($local_stock) . ' in stock</p>';
            }
            
            if ($turn14_stock > 0) {
                echo '<p style="margin: 5px 0;"><strong>📦 Turn14 Available:</strong> ' . esc_html($turn14_stock) . ' available</p>';
            }
            
            echo '</div>';
        }
    }
}

new T14SF_Stock_Manager();
