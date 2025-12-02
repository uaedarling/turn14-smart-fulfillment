<?php
/**
 * Main Settings/Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['t14sf_save_settings'])) {
    check_admin_referer('t14sf_settings_nonce');
    
    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : '';
    $local_methods = isset($_POST['local_methods']) ? array_map('sanitize_text_field', $_POST['local_methods']) : array();
    
    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);
    
    echo '<div class="notice notice-success"><p><strong>Settings saved successfully!</strong></p></div>';
}

$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));

global $wpdb;
$total_products = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'product' 
    AND post_status = 'publish'
");

$products_with_local_stock = $wpdb->get_var("
    SELECT COUNT(DISTINCT post_id) 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = '_stock' 
    AND CAST(meta_value AS UNSIGNED) > 0
");

$products_with_turn14_price = $wpdb->get_var("
    SELECT COUNT(DISTINCT post_id) 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = '_turn14_price' 
    AND meta_value != ''");

$products_with_turn14_stock = $wpdb->get_var("
    SELECT COUNT(DISTINCT post_id) 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = '_turn14_stock' 
    AND CAST(meta_value AS UNSIGNED) > 0
");

$shipping_methods = WC()->shipping()->get_shipping_methods();
?>

<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    <p class="description">Intelligent stock, pricing, and shipping management for Turn14 integration.</p>
    
    <div class="t14sf-stats-grid">
        <div class="t14sf-stat-card">
            <div class="t14sf-stat-icon">📦</div>
            <div class="t14sf-stat-content">
                <h3>Total Products</h3>
                <p class="t14sf-stat-number"><?php echo number_format($total_products); ?></p>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-success">
            <div class="t14sf-stat-icon">🏭</div>
            <div class="t14sf-stat-content">
                <h3>Local Stock</h3>
                <p class="t14sf-stat-number"><?php echo number_format($products_with_local_stock); ?></p>
                <small><?php echo $total_products > 0 ? round(($products_with_local_stock / $total_products) * 100, 1) : 0; ?>%</small>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-primary">
            <div class="t14sf-stat-icon">💰</div>
            <div class="t14sf-stat-content">
                <h3>Turn14 Prices</h3>
                <p class="t14sf-stat-number"><?php echo number_format($products_with_turn14_price); ?></p>
                <small><?php echo $total_products > 0 ? round(($products_with_turn14_price / $total_products) * 100, 1) : 0; ?>%</small>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-warning">
            <div class="t14sf-stat-icon">📋</div>
            <div class="t14sf-stat-content">
                <h3>Turn14 Stock</h3>
                <p class="t14sf-stat-number"><?php echo number_format($products_with_turn14_stock); ?></p>
                <small><?php echo $total_products > 0 ? round(($products_with_turn14_stock / $total_products) * 100, 1) : 0; ?>%</small>
            </div>
        </div>
    </div>
    
    <form method="post" class="t14sf-settings-form">
        <?php wp_nonce_field('t14sf_settings_nonce'); ?>
        
        <div class="t14sf-settings-section">
            <h2>💰 Price Management</h2>
            <p class="description">Control how product prices are displayed based on stock availability.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="price_mode">Price Display Mode</label>
                    </th>
                    <td>
                        <select name="price_mode" id="price_mode" class="regular-text">
                            <option value="auto" <?php selected($price_mode, 'auto'); ?>>
                                Auto (Based on Stock) - Recommended
                            </option>
                            <option value="always_local" <?php selected($price_mode, 'always_local'); ?>>
                                Always Show Local Price
                            </option>
                            <option value="always_turn14" <?php selected($price_mode, 'always_turn14'); ?>>
                                Always Show Turn14 Price
                            </option>
                            <option value="manual" <?php selected($price_mode, 'manual'); ?>>
                                Manual (No Automatic Switching)
                            </option>
                        </select>
                        <p class="description">
                            <strong>Auto:</strong> Shows local price when stock > threshold, Turn14 price otherwise.<br>
                            <strong>Always Local:</strong> Always displays _local_price meta value.<br>
                            <strong>Always Turn14:</strong> Always displays _turn14_price meta value.<br>
                            <strong>Manual:</strong> Uses WooCommerce default price, no automatic switching.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stock_threshold">Stock Threshold</label>
                    </th>
                    <td>
                        <input type="number" name="stock_threshold" id="stock_threshold" 
                               value="<?php echo esc_attr($stock_threshold); ?>" 
                               min="0" step="1" class="small-text" />
                        <p class="description">
                            When local stock is <strong>≤ this number</strong>, switch to Turn14 price/shipping.<br>
                            <strong>0 (default):</strong> Switch only when completely out of stock.<br>
                            <strong>5:</strong> Switch when 5 or fewer items remain.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="t14sf-settings-section">
            <h2>🚚 Shipping Management</h2>
            <p class="description">Configure which shipping methods apply to local vs Turn14 packages.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="turn14_method_id">Turn14 Shipping Method ID</label>
                    </th>
                    <td>
                        <input type="text" name="turn14_method_id" id="turn14_method_id" 
                               value="<?php echo esc_attr($turn14_method_id); ?>" 
                               class="regular-text" placeholder="turn14_shipping" />
                        <p class="description">
                            The method_id of your Turn14 shipping plugin (e.g., "turn14_shipping").<br>
                            This will be used <strong>ONLY</strong> for products with stock ≤ threshold.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>Local Warehouse Shipping Methods</label>
                    </th>
                    <td>
                        <?php if (!empty($shipping_methods)): ?>
                            <?php foreach ($shipping_methods as $method_id => $method): ?>
                                <?php if ($method_id === $turn14_method_id) continue; ?>
                                <label style="display: block; margin: 8px 0;">
                                    <input type="checkbox" 
                                           name="local_methods[]" 
                                           value="<?php echo esc_attr($method_id); ?>"
                                           <?php checked(in_array($method_id, $local_methods)); ?> />
                                    <strong><?php echo esc_html($method->get_method_title()); ?></strong>
                                    <code style="color: #666;">(<?php echo esc_html($method_id); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description">No shipping methods found. Configure them in WooCommerce → Settings → Shipping.</p>
                        <?php endif; ?>
                        <p class="description" style="margin-top: 12px;">
                            Select which methods apply to items you have in stock locally.<br>
                            These will <strong>NOT</strong> be shown for Turn14 drop-ship items.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="t14sf-settings-section">
            <h2>📋 Meta Fields Reference</h2>
            <table class="t14sf-meta-table">
                <thead>
                    <tr>
                        <th>Meta Key</th>
                        <th>Description</th>
                        <th>Updated By</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>_stock</code></td>
                        <td>Local warehouse stock (WooCommerce native)</td>
                        <td>You / Your inventory system</td>
                    </tr>
                    <tr>
                        <td><code>_local_price</code></td>
                        <td>Your warehouse price</td>
                        <td>You / Manual override</td>
                    </tr>
                    <tr>
                        <td><code>_turn14_price</code></td>
                        <td>Turn14 price (with your markup applied)</td>
                        <td>Turn14 sync script</td>
                    </tr>
                    <tr>
                        <td><code>_turn14_stock</code></td>
                        <td>Turn14 available stock</td>
                        <td>Turn14 sync script</td>
                    </tr>
                    <tr>
                        <td><code>_turn14_data</code></td>
                        <td>Full Turn14 product data (JSON)</td>
                        <td>Turn14 sync script</td>
                    </tr>
                    <tr>
                        <td><code>_fulfillment_source</code></td>
                        <td>Order item meta: "local" or "turn14"</td>
                        <td>Auto-tagged at checkout</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <p class="submit">
            <button type="submit" name="t14sf_save_settings" class="button button-primary button-large">
                💾 Save Settings
            </button>
        </p>
    </form>
</div>
