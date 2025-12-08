<?php
/**
 * Product Meta Box
 * Shows Turn14 data on product edit page
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Product_Meta_Box {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_product', array($this, 'save_meta_box'), 10, 2);
    }
    
    public function add_meta_box() {
        add_meta_box(
            't14sf_product_data',
            '🚀 Turn14 Smart Fulfillment',
            array($this, 'render_meta_box'),
            'product',
            'side',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('t14sf_product_meta_nonce', 't14sf_product_meta_nonce');
        
        $product_id = $post->ID;
        $local_stock = get_post_meta($product_id, '_stock', true);
        $local_price = get_post_meta($product_id, '_local_price', true);
        $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
        $turn14_price = get_post_meta($product_id, '_turn14_price', true);
        $turn14_sku = get_post_meta($product_id, '_turn14_sku', true);
        
        $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
        $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
        
        $threshold = intval(Turn14_Smart_Fulfillment::get_option('stock_threshold', 0));
        $active_source = ($local_stock > $threshold) ? 'local' : 'turn14';
        ?>
        
        <style>
            .t14sf-meta-box { margin: -6px -12px; }
            .t14sf-meta-section { padding: 12px; border-bottom: 1px solid #ddd; }
            .t14sf-meta-section:last-child { border-bottom: none; }
            .t14sf-meta-row { display: flex; justify-content: space-between; margin: 8px 0; }
            .t14sf-meta-label { font-weight: 600; color: #666; }
            .t14sf-meta-value { font-weight: 700; }
            .t14sf-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
            .t14sf-badge-success { background: #d1fae5; color: #059669; }
            .t14sf-badge-primary { background: #dbeafe; color: #1e40af; }
            .t14sf-badge-danger { background: #fee2e2; color: #dc2626; }
            .t14sf-input-group { margin: 10px 0; }
            .t14sf-input-group label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600; color: #666; }
            .t14sf-input-group input { width: 100%; }
        </style>
        
        <div class="t14sf-meta-box">
            <div class="t14sf-meta-section">
                <div style="text-align: center; margin-bottom: 10px;">
                    <?php if ($active_source === 'local'): ?>
                        <span class="t14sf-badge t14sf-badge-success" style="font-size: 13px;"><?php echo esc_html_x('🏭 ACTIVE: LOCAL WAREHOUSE', 'meta box label', 'turn14-smart-fulfillment'); ?></span>
                    <?php else: ?>
                        <span class="t14sf-badge t14sf-badge-primary" style="font-size: 13px;"><?php echo esc_html_x('📦 ACTIVE: TURN14 DROP-SHIP', 'meta box label', 'turn14-smart-fulfillment'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Local Stock:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <span class="t14sf-badge <?php echo $local_stock > 0 ? 't14sf-badge-success' : 't14sf-badge-danger'; ?>">
                            <?php echo esc_html( $local_stock ); ?>
                        </span>
                    </span>
                </div>
                
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Turn14 Stock:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <span class="t14sf-badge <?php echo $turn14_stock > 0 ? 't14sf-badge-primary' : 't14sf-badge-danger'; ?>">
                            <?php echo esc_html( $turn14_stock ); ?>
                        </span>
                    </span>
                </div>
                
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Total Available:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <strong style="color: #10b981;"><?php echo esc_html( $local_stock + $turn14_stock ); ?></strong>
                    </span>
                </div>
            </div>
            
            <div class="t14sf-meta-section">
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Local Price:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <?php echo $local_price !== '' ? wp_kses_post( wc_price( floatval( $local_price ) ) ) : '&mdash;'; ?>
                    </span>
                </div>
                
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Turn14 Price:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <?php echo $turn14_price !== '' ? wp_kses_post( wc_price( floatval( $turn14_price ) ) ) : '&mdash;'; ?>
                    </span>
                </div>
                
                <?php if ($turn14_sku): ?>
                <div class="t14sf-meta-row">
                    <span class="t14sf-meta-label"><?php echo esc_html__('Turn14 SKU:', 'turn14-smart-fulfillment'); ?></span>
                    <span class="t14sf-meta-value">
                        <code><?php echo esc_html($turn14_sku); ?></code>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="t14sf-meta-section">
                <div class="t14sf-input-group">
                    <label for="t14sf_local_price_override"><?php echo esc_html__('Override Local Price', 'turn14-smart-fulfillment'); ?></label>
                    <input type="number" 
                           id="t14sf_local_price_override" 
                           name="t14sf_local_price_override" 
                           value="<?php echo esc_attr( $local_price !== '' ? floatval( $local_price ) : '' ); ?>" 
                           step="0.01" 
                           min="0"
                           placeholder="<?php echo esc_attr__('Leave empty for default', 'turn14-smart-fulfillment'); ?>" />
                    <p class="description" style="margin: 4px 0 0 0; font-size: 11px;">
                        <?php echo esc_html__('Set a custom price for local warehouse stock.', 'turn14-smart-fulfillment'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    public function save_meta_box($post_id, $post) {
        if (!isset($_POST['t14sf_product_meta_nonce']) || 
            !wp_verify_nonce($_POST['t14sf_product_meta_nonce'], 't14sf_product_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['t14sf_local_price_override'])) {
            $local_price_raw = sanitize_text_field($_POST['t14sf_local_price_override']);
            // Allow empty to clear override
            if ($local_price_raw !== '') {
                // Use floatval after sanitization; if WooCommerce available you could use wc_format_decimal()
                $local_price = floatval($local_price_raw);
                update_post_meta($post_id, '_local_price', $local_price);
            } else {
                delete_post_meta($post_id, '_local_price');
            }
        }
    }
}

new T14SF_Product_Meta_Box();
