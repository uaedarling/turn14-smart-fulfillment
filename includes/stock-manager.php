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
        add_filter('woocommerce_product_is_in_stock', array($this, 'is_product_in_stock'), 10, 2);
        add_filter('woocommerce_product_get_stock_status', array($this, 'get_stock_status'), 10, 2);
        add_action('woocommerce_after_add_to_cart_button', array($this, 'display_stock_source'));
    }
    
    public function is_product_in_stock($in_stock, $product) {
        $product_id = $product->get_id();
        
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        
        if ($local_stock > 0) {
            return true;
        }
        
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        return ($turn14_stock > 0);
    }
    
    public function get_stock_status($status, $product) {
        if ($this->is_product_in_stock(true, $product)) {
            return 'instock';
        }
        return 'outofstock';
    }
    
    public function display_stock_source() {
        global $product;
        
        if (! $product) return;
        
        $product_id = $product->get_id();
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        if ($local_stock > 0) {
            echo '<div class="t14sf-stock-notice t14sf-local-stock">';
            echo '✅ <strong>In Stock</strong> - Ships from our warehouse';
            echo '</div>';
        } elseif ($turn14_stock > 0) {
            echo '<div class="t14sf-stock-notice t14sf-dropship-stock">';
            echo '📦 <strong>Available</strong> - Drop-ships from supplier';
            echo '</div>';
        }
    }
}

new T14SF_Stock_Manager();