<?php
if (!defined('ABSPATH')) { exit; }

/** Contract for future CreditOS postal service integrations. No sending implementation. */
interface CreditOS_Mail_Provider_Interface {
    public function validate_package(array $package);
    public function submit(array $package, array $authorization);
    public function get_status($provider_job_id);
    public function verify_webhook(array $headers, $raw_body);
}

/** Inactive registry; no providers are connected by default. */
final class CreditOS_Mail_Provider_Registry {
    private static $providers = array();

    public static function register($key, CreditOS_Mail_Provider_Interface $provider) {
        if (!is_string($key) || !preg_match('/^[a-z0-9_]+$/', $key)) {
            return new WP_Error('creditos_invalid_provider', 'Invalid provider identifier.');
        }
        self::$providers[$key] = $provider;
        return true;
    }

    public static function get($key) {
        if (isset(self::$providers[$key])) {
            return self::$providers[$key];
        }
        return new WP_Error('creditos_provider_unavailable', 'Mail provider is not connected.');
    }

    public static function connected() {
        return !empty(self::$providers);
    }
}
