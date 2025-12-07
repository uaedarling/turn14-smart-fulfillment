<?php
/**
 * System Check Page
 * Provides comprehensive system requirements checker
 */

if (!defined('ABSPATH')) {
    exit;
}

// Perform system checks
$checks = array();

// 1. WordPress version check (5.8+)
$wp_version = get_bloginfo('version');
$checks['wordpress'] = array(
    'label' => __('WordPress Version', 'turn14-smart-fulfillment'),
    'required' => '5.8+',
    'current' => $wp_version,
    'status' => version_compare($wp_version, '5.8', '>=') ? 'pass' : 'fail',
    'message' => version_compare($wp_version, '5.8', '>=') 
        ? __('WordPress version meets requirements', 'turn14-smart-fulfillment')
        : sprintf(__('WordPress %s or higher is required', 'turn14-smart-fulfillment'), '5.8'),
    'action' => version_compare($wp_version, '5.8', '<') 
        ? array('url' => admin_url('update-core.php'), 'label' => __('Update WordPress', 'turn14-smart-fulfillment'))
        : null,
);

// 2. PHP version check (7.4+)
$php_version = PHP_VERSION;
$checks['php'] = array(
    'label' => __('PHP Version', 'turn14-smart-fulfillment'),
    'required' => '7.4+',
    'current' => $php_version,
    'status' => version_compare($php_version, '7.4', '>=') ? 'pass' : 'fail',
    'message' => version_compare($php_version, '7.4', '>=')
        ? __('PHP version meets requirements', 'turn14-smart-fulfillment')
        : sprintf(__('PHP %s or higher is required. Contact your hosting provider.', 'turn14-smart-fulfillment'), '7.4'),
    'action' => null,
);

// 3. WooCommerce installation and version check (5.0+)
$wc_active = class_exists('WooCommerce');
$wc_version = $wc_active ? WC()->version : __('Not Installed', 'turn14-smart-fulfillment');
$wc_version_ok = $wc_active && version_compare($wc_version, '5.0', '>=');

$checks['woocommerce'] = array(
    'label' => __('WooCommerce', 'turn14-smart-fulfillment'),
    'required' => '5.0+',
    'current' => $wc_version,
    'status' => $wc_version_ok ? 'pass' : 'fail',
    'message' => $wc_version_ok
        ? __('WooCommerce is installed and meets requirements', 'turn14-smart-fulfillment')
        : (!$wc_active 
            ? __('WooCommerce is not installed', 'turn14-smart-fulfillment')
            : sprintf(__('WooCommerce %s or higher is required', 'turn14-smart-fulfillment'), '5.0')),
    'action' => !$wc_active
        ? array('url' => admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'), 'label' => __('Install WooCommerce', 'turn14-smart-fulfillment'))
        : (!$wc_version_ok ? array('url' => admin_url('plugins.php'), 'label' => __('Update WooCommerce', 'turn14-smart-fulfillment')) : null),
);

// 4. WooCommerce shipping zones check
$shipping_zones_configured = false;
$shipping_zones_message = '';
if ($wc_active) {
    $zones = WC_Shipping_Zones::get_zones();
    $shipping_zones_configured = !empty($zones);
    $shipping_zones_message = $shipping_zones_configured
        ? sprintf(__('%d shipping zone(s) configured', 'turn14-smart-fulfillment'), count($zones))
        : __('No shipping zones configured', 'turn14-smart-fulfillment');
}

$checks['shipping_zones'] = array(
    'label' => __('Shipping Zones', 'turn14-smart-fulfillment'),
    'required' => __('At least 1 zone', 'turn14-smart-fulfillment'),
    'current' => $wc_active ? (count($zones) . ' ' . __('zones', 'turn14-smart-fulfillment')) : 'N/A',
    'status' => $shipping_zones_configured ? 'pass' : 'warning',
    'message' => $shipping_zones_message,
    'action' => !$shipping_zones_configured && $wc_active
        ? array('url' => admin_url('admin.php?page=wc-settings&tab=shipping'), 'label' => __('Configure Shipping', 'turn14-smart-fulfillment'))
        : null,
);

// 5. Product meta capabilities check
$can_manage_products = current_user_can('edit_products');
$checks['product_meta'] = array(
    'label' => __('Product Meta Capabilities', 'turn14-smart-fulfillment'),
    'required' => __('Edit Products', 'turn14-smart-fulfillment'),
    'current' => $can_manage_products ? __('Yes', 'turn14-smart-fulfillment') : __('No', 'turn14-smart-fulfillment'),
    'status' => $can_manage_products ? 'pass' : 'warning',
    'message' => $can_manage_products
        ? __('User can edit product metadata', 'turn14-smart-fulfillment')
        : __('User cannot edit product metadata', 'turn14-smart-fulfillment'),
    'action' => null,
);

// 6. Turn14 API credentials check
$api_endpoint = Turn14_Smart_Fulfillment::get_option('turn14_api_endpoint', '');
$api_key = Turn14_Smart_Fulfillment::get_option('turn14_api_key', '');
$credentials_set = !empty($api_endpoint) && !empty($api_key);

// Test API connection if credentials are set
$api_connection_ok = false;
$api_connection_message = '';
if ($credentials_set) {
    // Try to connect to the API
    $test_response = wp_remote_get(
        rtrim($api_endpoint, '/') . '/health',
        array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        )
    );
    
    if (!is_wp_error($test_response)) {
        $response_code = wp_remote_retrieve_response_code($test_response);
        $api_connection_ok = ($response_code >= 200 && $response_code < 300);
        $api_connection_message = $api_connection_ok
            ? __('API connection successful', 'turn14-smart-fulfillment')
            : sprintf(__('API returned status code: %d', 'turn14-smart-fulfillment'), $response_code);
    } else {
        $api_connection_message = sprintf(__('API connection failed: %s', 'turn14-smart-fulfillment'), $test_response->get_error_message());
    }
}

$checks['api_credentials'] = array(
    'label' => __('Turn14 API Credentials', 'turn14-smart-fulfillment'),
    'required' => __('Configured', 'turn14-smart-fulfillment'),
    'current' => $credentials_set 
        ? ($api_connection_ok ? __('Valid', 'turn14-smart-fulfillment') : __('Invalid', 'turn14-smart-fulfillment'))
        : __('Not Set', 'turn14-smart-fulfillment'),
    'status' => $credentials_set && $api_connection_ok ? 'pass' : ($credentials_set ? 'warning' : 'fail'),
    'message' => $credentials_set 
        ? $api_connection_message
        : __('API credentials not configured', 'turn14-smart-fulfillment'),
    'action' => array('url' => admin_url('admin.php?page=t14sf-dashboard'), 'label' => __('Configure API', 'turn14-smart-fulfillment')),
);

// 7. Plugin directory write permissions check
$plugin_dir_writable = is_writable(T14SF_PLUGIN_DIR);
$checks['permissions'] = array(
    'label' => __('Plugin Directory Permissions', 'turn14-smart-fulfillment'),
    'required' => __('Writable', 'turn14-smart-fulfillment'),
    'current' => $plugin_dir_writable ? __('Writable', 'turn14-smart-fulfillment') : __('Not Writable', 'turn14-smart-fulfillment'),
    'status' => $plugin_dir_writable ? 'pass' : 'warning',
    'message' => $plugin_dir_writable
        ? __('Plugin directory is writable', 'turn14-smart-fulfillment')
        : __('Plugin directory is not writable. Some features may not work correctly.', 'turn14-smart-fulfillment'),
    'action' => null,
);

// Calculate overall status
$fail_count = 0;
$warning_count = 0;
foreach ($checks as $check) {
    if ($check['status'] === 'fail') {
        $fail_count++;
    } elseif ($check['status'] === 'warning') {
        $warning_count++;
    }
}

$overall_status = ($fail_count === 0 && $warning_count === 0) ? 'ready' : ($fail_count > 0 ? 'action_required' : 'warnings');

?>

<div class="wrap t14sf-system-check">
    <h1><?php esc_html_e('🔍 System Check', 'turn14-smart-fulfillment'); ?></h1>
    <p class="description">
        <?php esc_html_e('Verify that your system meets all requirements for Turn14 Smart Fulfillment.', 'turn14-smart-fulfillment'); ?>
    </p>
    
    <!-- Overall Status Banner -->
    <div class="t14sf-status-banner t14sf-status-<?php echo esc_attr($overall_status); ?>">
        <?php if ($overall_status === 'ready'): ?>
            <div class="t14sf-status-icon">✅</div>
            <div class="t14sf-status-content">
                <h2><?php esc_html_e('Ready to Use', 'turn14-smart-fulfillment'); ?></h2>
                <p><?php esc_html_e('All system requirements are met. Your plugin is ready to use!', 'turn14-smart-fulfillment'); ?></p>
            </div>
        <?php elseif ($overall_status === 'warnings'): ?>
            <div class="t14sf-status-icon">⚠️</div>
            <div class="t14sf-status-content">
                <h2><?php esc_html_e('Ready with Warnings', 'turn14-smart-fulfillment'); ?></h2>
                <p><?php echo esc_html(sprintf(__('System has %d warning(s). The plugin will work, but some features may be limited.', 'turn14-smart-fulfillment'), $warning_count)); ?></p>
            </div>
        <?php else: ?>
            <div class="t14sf-status-icon">❌</div>
            <div class="t14sf-status-content">
                <h2><?php esc_html_e('Requires Action', 'turn14-smart-fulfillment'); ?></h2>
                <p><?php echo esc_html(sprintf(__('System has %d critical issue(s). Please fix them to use the plugin.', 'turn14-smart-fulfillment'), $fail_count)); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- System Checks Table -->
    <div class="t14sf-checks-container">
        <table class="t14sf-checks-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Status', 'turn14-smart-fulfillment'); ?></th>
                    <th><?php esc_html_e('Check', 'turn14-smart-fulfillment'); ?></th>
                    <th><?php esc_html_e('Required', 'turn14-smart-fulfillment'); ?></th>
                    <th><?php esc_html_e('Current', 'turn14-smart-fulfillment'); ?></th>
                    <th><?php esc_html_e('Details', 'turn14-smart-fulfillment'); ?></th>
                    <th><?php esc_html_e('Action', 'turn14-smart-fulfillment'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $key => $check): ?>
                <tr class="t14sf-check-row t14sf-check-<?php echo esc_attr($check['status']); ?>">
                    <td class="t14sf-check-status">
                        <?php if ($check['status'] === 'pass'): ?>
                            <span class="t14sf-status-icon-small">✅</span>
                        <?php elseif ($check['status'] === 'warning'): ?>
                            <span class="t14sf-status-icon-small">⚠️</span>
                        <?php else: ?>
                            <span class="t14sf-status-icon-small">❌</span>
                        <?php endif; ?>
                    </td>
                    <td class="t14sf-check-label">
                        <strong><?php echo esc_html($check['label']); ?></strong>
                    </td>
                    <td class="t14sf-check-required">
                        <code><?php echo esc_html($check['required']); ?></code>
                    </td>
                    <td class="t14sf-check-current">
                        <code><?php echo esc_html($check['current']); ?></code>
                    </td>
                    <td class="t14sf-check-message">
                        <?php echo esc_html($check['message']); ?>
                    </td>
                    <td class="t14sf-check-action">
                        <?php if ($check['action']): ?>
                            <a href="<?php echo esc_url($check['action']['url']); ?>" class="button button-small">
                                <?php echo esc_html($check['action']['label']); ?>
                            </a>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Action Buttons -->
    <div class="t14sf-actions">
        <a href="<?php echo esc_url(admin_url('admin.php?page=t14sf-dashboard')); ?>" class="button button-primary button-large">
            <?php esc_html_e('Go to Dashboard', 'turn14-smart-fulfillment'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=t14sf-system-check')); ?>" class="button button-secondary button-large">
            <?php esc_html_e('Re-run Check', 'turn14-smart-fulfillment'); ?>
        </a>
    </div>
</div>

<style>
/* System Check Styles */
.t14sf-system-check {
    max-width: 1400px;
}

.t14sf-status-banner {
    background: white;
    padding: 30px;
    margin: 30px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 24px;
    border-left: 6px solid;
}

.t14sf-status-banner.t14sf-status-ready {
    border-left-color: #10b981;
    background: linear-gradient(to right, #ecfdf5 0%, white 100%);
}

.t14sf-status-banner.t14sf-status-warnings {
    border-left-color: #f59e0b;
    background: linear-gradient(to right, #fffbeb 0%, white 100%);
}

.t14sf-status-banner.t14sf-status-action_required {
    border-left-color: #ef4444;
    background: linear-gradient(to right, #fef2f2 0%, white 100%);
}

.t14sf-status-icon {
    font-size: 60px;
    line-height: 1;
}

.t14sf-status-content h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
}

.t14sf-status-content p {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.t14sf-checks-container {
    background: white;
    padding: 24px;
    margin: 24px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.t14sf-checks-table {
    width: 100%;
    border-collapse: collapse;
}

.t14sf-checks-table thead th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.t14sf-checks-table tbody td {
    padding: 16px 12px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.t14sf-check-row.t14sf-check-pass {
    background: #f0fdf4;
}

.t14sf-check-row.t14sf-check-warning {
    background: #fffbeb;
}

.t14sf-check-row.t14sf-check-fail {
    background: #fef2f2;
}

.t14sf-check-status {
    text-align: center;
    width: 60px;
}

.t14sf-status-icon-small {
    font-size: 24px;
}

.t14sf-check-label {
    font-weight: 600;
    color: #1f2937;
}

.t14sf-check-required code,
.t14sf-check-current code {
    background: #1e293b;
    color: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.t14sf-check-message {
    color: #4b5563;
    font-size: 13px;
}

.t14sf-check-action {
    text-align: center;
    width: 150px;
}

.t14sf-actions {
    text-align: center;
    margin: 30px 0;
}

.t14sf-actions .button {
    margin: 0 8px;
}
</style>
