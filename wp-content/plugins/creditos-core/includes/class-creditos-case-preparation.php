<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Case Preparation
 *
 * Converts an already-qualified dispute candidate into a controlled internal
 * case-preparation record. This layer never generates or sends a dispute.
 */
class CreditOS_Case_Preparation {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        add_action( 'init', array( $this, 'ensure_schema' ), 6 );
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function ensure_schema() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $this->wpdb->prefix;
        $c = $this->wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$p}creditos_case_preparations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            report_id BIGINT UNSIGNED NOT NULL,
            tradeline_id BIGINT UNSIGNED NULL,
            collection_id BIGINT UNSIGNED NULL,
            dispute_item_id BIGINT UNSIGNED NOT NULL,
            case_status VARCHAR(30) NOT NULL DEFAULT 'preparing',
            issue_summary TEXT NULL,
            requested_resolution VARCHAR(60) NULL,
            preparation_notes TEXT NULL,
            prepared_by BIGINT UNSIGNED NOT NULL,
            prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY dispute_item_id (dispute_item_id),
            KEY client_id (client_id),
            KEY report_id (report_id),
            KEY tradeline_id (tradeline_id),
            KEY collection_id (collection_id),
            KEY case_status (case_status)
        ) $c;" );
    }

    public function register_routes() {
        register_rest_route( 'creditos/v1', '/dispute-candidates/(?P<id>\\d+)/case-preparation', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_case' ),
                'permission_callback' => array( $this, 'can_access_candidate' ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'prepare_case' ),
                'permission_callback' => array( $this, 'can_access_candidate' ),
            ),
        ) );
    }

    private function candidate( $id ) {
        $p = $this->wpdb->prefix;
        return $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT di.*,
                    COALESCE(t.creditor_name,c.collector_name) AS creditor_name,
                    t.account_number_masked,
                    COALESCE(t.review_status,c.review_status) AS review_status,
                    COALESCE(t.discrepancy_type,c.discrepancy_type) AS discrepancy_type,
                    COALESCE(t.discrepancy_field,c.discrepancy_field) AS discrepancy_field,
                    COALESCE(t.discrepancy_details,c.discrepancy_details) AS discrepancy_details
             FROM {$p}creditos_dispute_items di
             LEFT JOIN {$p}creditos_tradelines t ON t.id=di.tradeline_id AND t.report_id=di.report_id AND t.client_id=di.client_id
             LEFT JOIN {$p}creditos_collections c ON c.id=di.collection_id AND c.report_id=di.report_id AND c.client_id=di.client_id
             WHERE di.id=%d AND di.candidate_status='candidate' AND (t.id IS NOT NULL OR c.id IS NOT NULL)",
            absint( $id )
        ), ARRAY_A );
    }

    public function can_access_candidate( $request ) {
        if ( ! is_user_logged_in() ) return false;
        if ( current_user_can( 'creditos_manage_disputes' ) || current_user_can( 'manage_options' ) ) return true;
        $candidate = $this->candidate( $request['id'] );
        if ( ! $candidate ) return false;
        $client_id = (int) $this->wpdb->get_var( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}creditos_clients WHERE wp_user_id=%d LIMIT 1",
            get_current_user_id()
        ) );
        return $client_id > 0 && $client_id === (int) $candidate['client_id'];
    }

    private function readiness( $candidate ) {
        $p = $this->wpdb->prefix;
        if ( ! empty( $candidate['collection_id'] ) ) {
            $accepted = (int) $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}creditos_collection_evidence WHERE report_id=%d AND collection_id=%d AND client_id=%d AND review_status='accepted'",
                $candidate['report_id'], $candidate['collection_id'], $candidate['client_id']
            ) );
        } else {
            $accepted = (int) $this->wpdb->get_var( $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}creditos_tradeline_evidence WHERE report_id=%d AND tradeline_id=%d AND client_id=%d AND review_status='accepted'",
                $candidate['report_id'], $candidate['tradeline_id'], $candidate['client_id']
            ) );
        }
        $checks = array(
            'candidate_exists' => true,
            'potential_inaccuracy' => 'potential_inaccuracy' === $candidate['review_status'],
            'discrepancy_documented' => ! empty( $candidate['discrepancy_type'] ) && 'none' !== $candidate['discrepancy_type'],
            'accepted_evidence' => $accepted > 0,
        );
        return array( 'ready' => ! in_array( false, $checks, true ), 'checks' => $checks, 'accepted_evidence' => $accepted );
    }

    public function get_case( $request ) {
        $candidate = $this->candidate( $request['id'] );
        if ( ! $candidate ) return new WP_Error( 'creditos_candidate_missing', 'Dispute candidate not found.', array( 'status' => 404 ) );
        $case = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}creditos_case_preparations WHERE dispute_item_id=%d LIMIT 1",
            $candidate['id']
        ), ARRAY_A );
        return rest_ensure_response( array( 'candidate' => $candidate, 'readiness' => $this->readiness( $candidate ), 'case' => $case ) );
    }

    public function prepare_case( $request ) {
        $candidate = $this->candidate( $request['id'] );
        if ( ! $candidate ) return new WP_Error( 'creditos_candidate_missing', 'Dispute candidate not found.', array( 'status' => 404 ) );
        $readiness = $this->readiness( $candidate );
        if ( ! $readiness['ready'] ) {
            return new WP_Error( 'creditos_case_not_ready', 'Case preparation is locked until review, discrepancy, and accepted-evidence requirements are satisfied.', array( 'status' => 409, 'readiness' => $readiness ) );
        }

        $table = $this->wpdb->prefix . 'creditos_case_preparations';
        $existing = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE dispute_item_id=%d LIMIT 1", $candidate['id'] ), ARRAY_A );
        if ( $existing ) return rest_ensure_response( array( 'created' => false, 'case' => $existing, 'readiness' => $readiness ) );

        $issue = sanitize_textarea_field( (string) $request->get_param( 'issue_summary' ) );
        if ( '' === $issue ) {
            $issue = trim( sprintf( '%s%s%s',
                $candidate['discrepancy_type'] ? ucwords( str_replace( '_', ' ', $candidate['discrepancy_type'] ) ) : 'Potential reporting inaccuracy',
                $candidate['discrepancy_field'] ? ' — ' . ucwords( str_replace( '_', ' ', $candidate['discrepancy_field'] ) ) : '',
                $candidate['discrepancy_details'] ? ': ' . $candidate['discrepancy_details'] : ''
            ) );
        }
        $resolution = sanitize_key( (string) $request->get_param( 'requested_resolution' ) );
        $allowed = array( 'investigate', 'correct', 'delete_if_unverifiable', 'update', 'other' );
        if ( ! in_array( $resolution, $allowed, true ) ) $resolution = 'investigate';

        $ok = $this->wpdb->insert( $table, array(
            'client_id' => (int) $candidate['client_id'],
            'report_id' => (int) $candidate['report_id'],
            'tradeline_id' => ! empty( $candidate['tradeline_id'] ) ? (int) $candidate['tradeline_id'] : null,
            'collection_id' => ! empty( $candidate['collection_id'] ) ? (int) $candidate['collection_id'] : null,
            'dispute_item_id' => (int) $candidate['id'],
            'case_status' => 'preparing',
            'issue_summary' => $issue,
            'requested_resolution' => $resolution,
            'preparation_notes' => sanitize_textarea_field( (string) $request->get_param( 'preparation_notes' ) ),
            'prepared_by' => get_current_user_id(),
            'prepared_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ) );
        if ( ! $ok ) return new WP_Error( 'creditos_case_create_failed', 'Case preparation could not be created.', array( 'status' => 500 ) );

        $case_id = (int) $this->wpdb->insert_id;
        $case = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $case_id ), ARRAY_A );
        return rest_ensure_response( array(
            'created' => true,
            'case' => $case,
            'readiness' => $readiness,
            'notice' => 'Internal case preparation created. No dispute was generated or sent.',
        ) );
    }
}
new CreditOS_Case_Preparation();
