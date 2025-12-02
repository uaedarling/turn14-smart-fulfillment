<?php
/**
 * Price Manager
 * Handles automatic price switching between local and Turn14 prices
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Price_Manager {
    
    public function __construct() {
        add_filter('woocommerce_product_get_price', array($this, 'get_effective_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'get_effective_price'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'get_effective_sale_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'get_effective_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'get_effective_price'), 10, 2);
    }
    
    public function get_effective_price($price, $product) {
        $product_id = $product->get_id();
        $price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
        
        if ($price_mode === 'manual') {
            return $price;
        }
        
        if ($price_mode === 'always_local') {
            return $this->get_local_price($product_id, $price);
        }
        
        if ($price_mode === 'always_turn14') {
            return $this->get_turn14_price($product_id, $price);
        }
        
        $local_stock = $this->get_local_stock($product_id);
        $threshold = intval(Turn14_Smart_Fulfillment::get_option('stock_threshold', 0));
        
        if ($local_stock > $threshold) {
            return $this->get_local_price($product_id, $price);
        } else {
            return $this->get_turn14_price($product_id, $price);
        }
    }
    
    public function get_effective_sale_price($price, $product) {
        return $this->get_effective_price($price, $product);
    }
    
    private function get_local_price($product_id, $fallback) {
        $local_price = get_post_meta($product_id, '_local_price', true);
        
        if ($local_price !== '' && is_numeric($local_price)) {
            return floatval($local_price);
        }
        
        return $fallback;
    }
    
    private function get_turn14_price($product_id, $fallback) {
        $turn14_price = get_post_meta($product_id, '_turn14_price', true);
        
        if ($turn14_price !== '' && is_numeric($turn14_price)) {
            return floatval($turn14_price);
        }
        
        return $fallback;
    }
    
    private function get_local_stock($product_id) {
        $stock = get_post_meta($product_id, '_stock', true);
        return ($stock === '') ? 0 : intval($stock);
    }
}

new T14SF_Price_Manager();