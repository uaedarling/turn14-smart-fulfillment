<?php
/**
 * Turn14 API Configuration Helper
 * Manages API credentials, token generation, and connection testing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Turn14_API_Config {
    
    /**
     * Get Client ID from WordPress options
     * 
     * @return string Client ID
     */
    public static function get_client_id() {
        return get_option('t14sf_api_client_id', 'c387a85cac64c9c4fa6d57a5ef0dfb8ad97a6d26');
    }
    
    /**
     * Get Client Secret from WordPress options
     * 
     * @return string Client Secret
     */
    public static function get_client_secret() {
        return get_option('t14sf_api_client_secret', '15469b8ad5adf0c1f9d48faa969dc5a6d6e59e9e');
    }
    
    /**
     * Get API Base URL from WordPress options
     * 
     * @return string API Base URL
     */
    public static function get_base_url() {
        return get_option('t14sf_api_base_url', 'https://apitest.turn14.com');
    }
    
    /**
     * Get Currency Rate from WordPress options
     * 
     * @return float Currency Rate
     */
    public static function get_currency_rate() {
        return floatval(get_option('t14sf_api_currency_rate', 3.699));
    }
    
    /**
     * Get API token (fetches new token if not cached or expired)
     * Caches token for 55 minutes using WordPress transients
     * 
     * @return string|false API token on success, false on failure
     */
    public static function get_token() {
        // Check for cached token
        $cached_token = get_transient('t14sf_api_token');
        if ($cached_token !== false) {
            return $cached_token;
        }
        
        // Fetch new token
        $client_id = self::get_client_id();
        $client_secret = self::get_client_secret();
        $base_url = self::get_base_url();
        
        if (empty($client_id) || empty($client_secret) || empty($base_url)) {
            error_log('Turn14 API Config: Missing credentials');
            return false;
        }
        
        $url = rtrim($base_url, '/') . '/v1/token';
        
        $args = array(
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ),
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Turn14 API token request error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            error_log('Turn14 API token non-200 response (' . $code . '): ' . $body);
            return false;
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Turn14 API token JSON decode error: ' . json_last_error_msg());
            return false;
        }
        
        if (empty($data['access_token'])) {
            error_log('Turn14 API token response missing access_token');
            return false;
        }
        
        $token = $data['access_token'];
        
        // Cache token for 55 minutes (3300 seconds)
        set_transient('t14sf_api_token', $token, 3300);
        
        return $token;
    }
    
    /**
     * Test API connection by fetching brands list
     * 
     * @return array Array with 'success' (bool) and 'message' (string) keys
     */
    public static function test_connection() {
        $token = self::get_token();
        
        if ($token === false) {
            return array(
                'success' => false,
                'message' => 'Failed to obtain API token. Please check your credentials.',
            );
        }
        
        $base_url = self::get_base_url();
        $url = rtrim($base_url, '/') . '/v1/brands';
        
        $args = array(
            'method'  => 'GET',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $response->get_error_message(),
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return array(
                'success' => false,
                'message' => 'API returned error code ' . $code . '. Please verify your credentials.',
            );
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Invalid API response format.',
            );
        }
        
        // Success - connection is working
        return array(
            'success' => true,
            'message' => 'API connection successful! Authentication verified and API is responding correctly.',
        );
    }
    
    /**
     * Clear cached API token
     * Should be called when credentials are updated
     */
    public static function clear_token_cache() {
        delete_transient('t14sf_api_token');
    }
}
