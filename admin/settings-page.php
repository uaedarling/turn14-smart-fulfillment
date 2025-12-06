<?php
/**
 * Main Settings/Dashboard Page
 * Fixed for stability and PHP 8+ compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- 1. Handle Form Submission ---
if (isset($_POST['t14sf_save_settings'])) {
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'turn14-smart-fulfillment'));
    }

    check_admin_referer('t14sf_settings_nonce');

    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : '';
    
    // Safety: Force array type and sanitize
    $raw_methods = isset($_POST['local_methods']) ? $_POST['local_methods'] : array();
    $local_methods = is_array($raw_methods) ? array_map('sanitize_text_field', $raw_methods) : array();

    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);

    // Redirect
    wp_safe_redirect(admin_url('admin.php?page=t14sf-dashboard&settings-updated=true'));
    exit;
}

// --- 2. Retrieve Current Options ---
$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');

// Safety: Ensure local_methods is ALWAYS an array
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
if (!is_array($local_methods)) {
    $local_methods = (array) $local_methods;
}

// --- 3. Get Stats Data ---
global $wpdb;
$total_products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
$products_with_local_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_stock' AND CAST(meta_value AS UNSIGNED) > 0");
$products_with_turn14_price = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_price' AND meta_value != ''");
$products_with_turn14_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_stock' AND CAST(meta_value AS UNSIGNED) > 0");

// --- 4. Get WC Shipping Methods ---
$wc_shipping_methods = array();
if (function_exists('WC') && WC()->shipping()) {
    $wc_methods = WC()->shipping()->get_shipping_methods();
    foreach($wc_methods as $method) {
        $wc_shipping_methods[$method->id] = $method->method_title;
    }
}

// --- 5. Add Admin CSS ---
add_action('admin_head', function() {
    ?>
    <style>
    .t14sf-dashboard {
        max-width: 1200px;
        margin: 20px auto;
    }
    
    .t14sf-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin: 30px 0;
    }
    
    .t14sf-stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    }
    
    .t14sf-stat-icon {
        font-size: 32px;
        margin-right: 20px;
    }
    
    .t14sf-stat-content h3 {
        margin: 0 0 5px 0;
        font-size: 14px;
        color: #646970;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .t14sf-stat-number {
        font-size: 28px;
        font-weight: 600;
        margin: 0;
        color: #1d2327;
    }
    
    .t14sf-stat-success .t14sf-stat-number {
        color: #00a32a;
    }
    
    .t14sf-stat-primary .t14sf-stat-number {
        color: #2271b1;
    }
    
    .t14sf-stat-warning .t14sf-stat-number {
        color: #dba617;
    }
    
    .t14sf-settings-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 25px;
        margin: 30px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    }
    
    .t14sf-settings-section h2 {
        margin-top: 0;
        padding-bottom: 15px;
        border-bottom: 1px solid #dcdcde;
    }
    
    .t14sf-settings-section .form-table {
        margin-top: 15px;
    }
    
    .t14sf-settings-section th {
        width: 250px;
    }
    </style>
    <?php
});

// --- 6. Render Page ---
?>
<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
    <?php endif; ?>

    <p class="description">Intelligent stock, pricing, and shipping management for Turn14 integration.</p>
    
    <div class="t14sf-stats-grid">
        <div class="t14sf-stat-card">
            <div class="t14sf-stat-icon">📦</div>
            <div class="t14sf-stat-content">
                <h3>Total Products</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $total_products ) ); ?></p>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-success">
            <div class="t14sf-stat-icon">🏭</div>
            <div class="t14sf-stat-content">
                <h3>Local Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_local_stock ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-primary">
            <div class="t14sf-stat-icon">💲</div>
            <div class="t14sf-stat-content">
                <h3>T14 Prices</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_price ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-warning">
            <div class="t14sf-stat-icon">🚚</div>
            <div class="t14sf-stat-content">
                <h3>T14 Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_stock ) ); ?></p>
            </div>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('t14sf_settings_nonce'); ?>
        <input type="hidden" name="t14sf_save_settings" value="1">

        <div class="t14sf-settings-section">
            <h2>Price & Stock Management</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="price_mode">Price Display Mode</label></th>
                    <td>
                        <select name="price_mode" id="price_mode" class="regular-text">
                            <option value="auto" <?php selected($price_mode, 'auto'); ?>>Auto (Recommended)</option>
                            <option value="always_local" <?php selected($price_mode, 'always_local'); ?>>Always Local</option>
                            <option value="always_turn14" <?php selected($price_mode, 'always_turn14'); ?>>Always Turn14</option>
                            <option value="manual" <?php selected($price_mode, 'manual'); ?>>Manual</option>
                        </select>
                        <p class="description">
                            <strong>Auto:</strong> Shows Local Price when stock > threshold, otherwise shows Turn14 Price.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="stock_threshold">Stock Threshold</label></th>
                    <td>
                        <input type="number" name="stock_threshold" id="stock_threshold" value="<?php echo esc_attr($stock_threshold); ?>" class="small-text" min="0">
                        <p class="description">Switch to Turn14 fulfillment when local stock drops to this level or below.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="t14sf-settings-section">
            <h2>Shipping Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="turn14_method_id">Turn14 Shipping Method ID</label></th>
                    <td>
                        <input type="text" name="turn14_method_id" id="turn14_method_id" value="<?php echo esc_attr($turn14_method_id); ?>" class="regular-text">
                        <p class="description">The method ID (slug) used by your Turn14 shipping plugin (e.g., <code>turn14_shipping</code>).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="local_methods">Local Shipping Methods</label></th>
                    <td>
                        <p class="description" style="margin-bottom: 15px;">Select methods available for Local Warehouse items:</p>
                        <fieldset>
                            <?php 
                            if (!empty($wc_shipping_methods)) {
                                foreach ($wc_shipping_methods as $id => $title) {
                                    ?>
                                    <label style="display:block; margin-bottom: 10px; padding: 5px 0;">
                                        <input type="checkbox" name="local_methods[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $local_methods)); ?>>
                                        <?php echo esc_html($title); ?> (<code><?php echo esc_html($id); ?></code>)
                                    </label>
                                    <?php 
                                }
                            } else {
                                echo '<p class="description">No shipping methods found. Please configure WooCommerce Shipping zones first.</p>';
                            }
                            ?>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('Save Settings', 'primary', 'submit', true); ?>
    </form>
</div>
