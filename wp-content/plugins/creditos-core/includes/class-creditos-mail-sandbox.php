<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Non-transmitting CreditOS mail sandbox.
 * This adapter never performs HTTP requests, prints mail, or issues real tracking.
 * It is deliberately not registered automatically.
 */
final class CreditOS_Mail_Sandbox_Provider implements CreditOS_Mail_Provider_Interface {
    public function validate_package(array $package) {
        if (empty($package['draft_id']) || empty($package['revision']) || empty($package['recipient'])) {
            return new WP_Error('creditos_sandbox_package_incomplete', 'Draft, revision and recipient are required.');
        }
        if (empty($package['qa_fixture'])) {
            return new WP_Error('creditos_sandbox_fixture_only', 'Sandbox adapter accepts QA fixtures only.');
        }
        return array('valid'=>true, 'mode'=>'sandbox', 'transmits_mail'=>false);
    }

    public function submit(array $package, array $authorization) {
        $valid=$this->validate_package($package);
        if (is_wp_error($valid)) { return $valid; }
        if (empty($authorization['sandbox_acknowledged'])) {
            return new WP_Error('creditos_sandbox_ack_required', 'Explicit sandbox acknowledgement is required.');
        }
        return array(
            'mode'=>'sandbox',
            'status'=>'simulated_only',
            'accepted_by_carrier'=>false,
            'transmits_mail'=>false,
            'provider_job_id'=>'',
            'tracking_number'=>'',
            'message'=>'Sandbox validation completed. No provider request or mailing occurred.'
        );
    }

    public function get_status($provider_job_id) {
        return new WP_Error('creditos_sandbox_no_tracking', 'Sandbox has no carrier jobs or tracking events.');
    }

    public function verify_webhook(array $headers, $raw_body) {
        return false;
    }
}
