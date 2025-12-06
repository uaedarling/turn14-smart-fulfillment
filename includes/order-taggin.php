<?php
/**
 * Order Tagging
 * Tags order items with fulfillment source
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Order_Tagging {
    
    public function __construct() {
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'tag_order_item'), 10, 4);
        add_action('woocommerce_after_order_itemmeta', array($this, 'display_fulfillment_source'), 10, 3);
    }
    
    public function tag_order_item($item, $cart_item_key, $values, $order) {
        $product = $values['data'];
        $product_id = $product->get_id();
        $threshold = intval(Turn14_Smart_Fulfillment::get_option('stock_threshold', 0));
        
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        
        if ($local_stock > $threshold) {
            $source = 'local';
        } else {
            $source = 'turn14';
        }
        
        $item->add_meta_data('_fulfillment_source', $source, true);
        $item->add_meta_data('_stock_at_purchase', $local_stock, true);
    }
    
    public function display_fulfillment_source($item_id, $item, $product) {
        $source = wc_get_order_item_meta($item_id, '_fulfillment_source', true);
        
        if ($source) {
            $label = ($source === 'local') ? '🏭 Local Warehouse' : '📦 Turn14 Drop-Ship';
            $color = ($source === 'local') ? '#10b981' : '#667eea';
            
            echo '<div style="margin-top:10px;padding:8px;background:' . $color . ';color:white;border-radius:4px;font-size:12px;font-weight:600;">';
            echo esc_html($label);
            echo '</div>';
        }
    }
}

new T14SF_Order_Tagging();
