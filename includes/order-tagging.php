<?php
if (! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Order Tagging
 * Tags order items with fulfillment source
 */

class T14SF_Order_Tagging {

    public function __construct() {
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'tag_order_item' ), 10, 4 );
        add_action( 'woocommerce_after_order_itemmeta', array( $this, 'display_fulfillment_source' ), 10, 3 );
    }

    /**
     * Tag order item with fulfillment source
     *
     * @param WC_Order_Item_Product $item
     * @param string               $cart_item_key
     * @param array                $values
     * @param WC_Order             $order
     */
    public function tag_order_item( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['data'] ) || ! is_object( $values['data'] ) ) {
            return;
        }

        $product = $values['data'];
        $product_id = $product->get_id();
        $threshold = intval( Turn14_Smart_Fulfillment::get_option( 'stock_threshold', 0 ) );

        $local_stock = get_post_meta( $product_id, '_stock', true );
        $local_stock = ( $local_stock === '' ) ? 0 : intval( $local_stock );
        
        $turn14_stock = get_post_meta( $product_id, '_turn14_stock', true );
        $turn14_stock = ( $turn14_stock === '' ) ? 0 : intval( $turn14_stock );
        
        $quantity = $item->get_quantity();

        // Determine fulfillment source based on availability
        if ( $local_stock > $threshold && $local_stock >= $quantity ) {
            $source = 'local';
            $source_label = 'Local Warehouse';
        } elseif ( $turn14_stock >= $quantity ) {
            $source = 'turn14';
            $source_label = 'Turn14 Drop-Ship';
        } else {
            $source = 'backorder';
            $source_label = 'Backorder';
        }

        $item->add_meta_data( '_fulfillment_source', $source, true );
        $item->add_meta_data( '_fulfillment_label', $source_label, true );
        $item->add_meta_data( '_local_stock_at_purchase', $local_stock, true );
        $item->add_meta_data( '_turn14_stock_at_purchase', $turn14_stock, true );
    }

    /**
     * Display fulfillment source in admin order view
     *
     * @param int                $item_id
     * @param WC_Order_Item      $item
     * @param WC_Product|null    $product
     */
    public function display_fulfillment_source( $item_id, $item, $product ) {
        $source = wc_get_order_item_meta( $item_id, '_fulfillment_source', true );

        if ( ! $source ) {
            return;
        }

        // Determine label and color based on source
        if ( $source === 'local' ) {
            $label = '🏭 Local Warehouse';
            $color = '#10b981';
        } elseif ( $source === 'turn14' ) {
            $label = '📦 Turn14 Drop-Ship';
            $color = '#667eea';
        } else {
            $label = '⏳ Backorder';
            $color = '#f59e0b';
        }

        echo '<div style="margin-top:8px;padding:6px 8px;background:' . esc_attr( $color ) . ';color:#fff;border-radius:4px;font-size:12px;font-weight:600;">';
        echo esc_html( $label );
        echo '</div>';
    }
}

// Initialize
new T14SF_Order_Tagging();
