<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CreditOS_Report_Import {
    private $repository;
    private $wpdb;

    public function __construct( CreditOS_Repository $repository ) {
        global $wpdb;
        $this->repository = $repository;
        $this->wpdb = $wpdb;
        add_action( 'init', array( $this, 'maybe_install_schema' ), 5 );
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function maybe_install_schema() {
        if ( get_option( 'creditos_reports_schema_version' ) === CREDITOS_CORE_VERSION ) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $this->wpdb->prefix;
        $c = $this->wpdb->get_charset_collate();
        $tables = array(
            "CREATE TABLE {$p}creditos_credit_reports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NOT NULL DEFAULT 'multi',
                provider VARCHAR(80) NOT NULL DEFAULT 'manual_upload',
                report_date DATE NULL,
                imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(30) NOT NULL DEFAULT 'uploaded',
                parser_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                source_attachment_id BIGINT UNSIGNED NULL,
                source_format VARCHAR(20) NULL,
                source_filename VARCHAR(255) NULL,
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY client_id (client_id), KEY status (status), KEY parser_status (parser_status)
            ) $c;",
            "CREATE TABLE {$p}creditos_credit_report_sources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                report_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NULL,
                source_type VARCHAR(40) NOT NULL DEFAULT 'upload',
                provider VARCHAR(80) NULL,
                raw_reference VARCHAR(255) NULL,
                checksum VARCHAR(128) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY report_id (report_id)
            ) $c;",
            "CREATE TABLE {$p}creditos_tradelines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                report_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NULL,
                creditor_name VARCHAR(190) NOT NULL,
                account_number_masked VARCHAR(80) NULL,
                account_type VARCHAR(80) NULL,
                opened_date DATE NULL,
                status VARCHAR(100) NULL,
                balance DECIMAL(14,2) NULL,
                credit_limit DECIMAL(14,2) NULL,
                past_due DECIMAL(14,2) NULL,
                payment_status VARCHAR(120) NULL,
                date_reported DATE NULL,
                remarks TEXT NULL,
                responsibility VARCHAR(80) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY report_id (report_id), KEY client_id (client_id), KEY bureau (bureau)
            ) $c;",
            "CREATE TABLE {$p}creditos_collections (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                report_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NULL,
                collector_name VARCHAR(190) NOT NULL,
                original_creditor VARCHAR(190) NULL,
                balance DECIMAL(14,2) NULL,
                assigned_date DATE NULL,
                status VARCHAR(100) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY report_id (report_id), KEY client_id (client_id)
            ) $c;",
            "CREATE TABLE {$p}creditos_inquiries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                report_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NULL,
                creditor_name VARCHAR(190) NOT NULL,
                inquiry_type VARCHAR(40) NULL,
                inquiry_date DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY report_id (report_id), KEY client_id (client_id)
            ) $c;",
            "CREATE TABLE {$p}creditos_personal_information (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                report_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                bureau VARCHAR(40) NULL,
                info_type VARCHAR(40) NOT NULL,
                info_value VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'reported',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), KEY report_id (report_id), KEY client_id (client_id), KEY info_type (info_type)
            ) $c;"
        );
        foreach ( $tables as $sql ) dbDelta( $sql );
        update_option( 'creditos_reports_schema_version', CREDITOS_CORE_VERSION );
    }

    public function register_routes() {
        register_rest_route( 'creditos/v1', '/reports', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'list_reports' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
        register_rest_route( 'creditos/v1', '/reports/import', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'import_report' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
        register_rest_route( 'creditos/v1', '/reports/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_report' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
        register_rest_route( 'creditos/v1', '/reports/(?P<id>\d+)/normalized', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'save_normalized' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
    }

    public function logged_in() { return is_user_logged_in(); }

    private function current_client() {
        return $this->repository->get_or_create_client_for_user( get_current_user_id() );
    }

    private function consent_is_valid( $client_id ) {
        $table = $this->wpdb->prefix . 'creditos_onboarding';
        return (bool) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE client_id=%d AND consented_at IS NOT NULL LIMIT 1", $client_id ) );
    }

    public function list_reports() {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );
        $table = $this->wpdb->prefix . 'creditos_credit_reports';
        $rows = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,bureau,provider,report_date,imported_at,status,parser_status,source_format,source_filename,error_message FROM {$table} WHERE client_id=%d ORDER BY imported_at DESC,id DESC", $client->id ), ARRAY_A );
        return rest_ensure_response( array( 'reports' => $rows ) );
    }

    public function import_report( WP_REST_Request $request ) {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );
        if ( ! $this->consent_is_valid( $client->id ) ) return new WP_Error( 'creditos_report_consent_required', 'Complete CreditOS onboarding and consent before importing a credit report.', array( 'status' => 403 ) );

        $files = $request->get_file_params();
        if ( empty( $files['report'] ) || empty( $files['report']['tmp_name'] ) ) return new WP_Error( 'creditos_report_required', 'Choose a credit report file to import.', array( 'status' => 400 ) );
        $file = $files['report'];
        if ( ! empty( $file['size'] ) && (int) $file['size'] > 25 * MB_IN_BYTES ) return new WP_Error( 'creditos_report_too_large', 'Credit report files must be 25 MB or smaller.', array( 'status' => 400 ) );

        $allowed = array( 'pdf' => 'application/pdf', 'json' => 'application/json', 'csv' => 'text/csv' );
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
        $ext = strtolower( $check['ext'] ?: pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! isset( $allowed[ $ext ] ) ) return new WP_Error( 'creditos_report_type', 'Supported report formats are PDF, JSON, and CSV.', array( 'status' => 400 ) );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload( 'report', 0, array( 'post_title' => sanitize_file_name( $file['name'] ), 'post_author' => get_current_user_id() ), array( 'test_form' => false, 'mimes' => $allowed ) );
        if ( is_wp_error( $attachment_id ) ) return $attachment_id;

        update_post_meta( $attachment_id, '_creditos_private', '1' );
        $bureau = sanitize_key( $request->get_param( 'bureau' ) ?: 'multi' );
        if ( ! in_array( $bureau, array( 'experian','equifax','transunion','multi' ), true ) ) $bureau = 'multi';
        $report_date = sanitize_text_field( $request->get_param( 'report_date' ) ?: '' );
        $now = current_time( 'mysql' );
        $table = $this->wpdb->prefix . 'creditos_credit_reports';
        $this->wpdb->insert( $table, array(
            'client_id' => absint( $client->id ), 'bureau' => $bureau, 'provider' => 'manual_upload',
            'report_date' => $report_date ?: null, 'imported_at' => $now, 'status' => 'uploaded',
            'parser_status' => 'json' === $ext ? 'processing' : 'pending_review', 'source_attachment_id' => absint( $attachment_id ),
            'source_format' => $ext, 'source_filename' => sanitize_file_name( $file['name'] ), 'created_at' => $now, 'updated_at' => $now,
        ) );
        $report_id = absint( $this->wpdb->insert_id );
        $path = get_attached_file( $attachment_id );
        $this->wpdb->insert( $this->wpdb->prefix . 'creditos_credit_report_sources', array(
            'report_id' => $report_id, 'bureau' => $bureau, 'source_type' => 'upload', 'provider' => 'manual_upload',
            'raw_reference' => (string) $attachment_id, 'checksum' => $path && file_exists( $path ) ? hash_file( 'sha256', $path ) : null, 'created_at' => $now,
        ) );
        $this->repository->audit( get_current_user_id(), $client->id, 'credit_report_uploaded', 'credit_report', $report_id, array( 'bureau' => $bureau, 'format' => $ext ) );

        if ( 'json' === $ext && $path && file_exists( $path ) ) {
            $payload = json_decode( file_get_contents( $path ), true );
            if ( is_array( $payload ) ) {
                $result = $this->apply_normalized_payload( $report_id, $client->id, $payload );
                if ( is_wp_error( $result ) ) {
                    $this->wpdb->update( $table, array( 'parser_status' => 'failed', 'error_message' => $result->get_error_message(), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $report_id ) );
                }
            } else {
                $this->wpdb->update( $table, array( 'parser_status' => 'failed', 'error_message' => 'JSON could not be parsed.', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $report_id ) );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'report_id' => $report_id, 'report' => $this->report_payload( $report_id, $client->id ) ) );
    }

    public function get_report( WP_REST_Request $request ) {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );
        $payload = $this->report_payload( absint( $request['id'] ), $client->id );
        if ( ! $payload ) return new WP_Error( 'creditos_report_not_found', 'Credit report not found.', array( 'status' => 404 ) );
        return rest_ensure_response( $payload );
    }

    public function save_normalized( WP_REST_Request $request ) {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );
        $report_id = absint( $request['id'] );
        if ( ! $this->report_payload( $report_id, $client->id ) ) return new WP_Error( 'creditos_report_not_found', 'Credit report not found.', array( 'status' => 404 ) );
        $payload = $request->get_json_params();
        $result = $this->apply_normalized_payload( $report_id, $client->id, is_array( $payload ) ? $payload : array() );
        if ( is_wp_error( $result ) ) return $result;
        $this->repository->audit( get_current_user_id(), $client->id, 'credit_report_normalized', 'credit_report', $report_id );
        return rest_ensure_response( array( 'success' => true, 'report' => $this->report_payload( $report_id, $client->id ) ) );
    }

    private function clean_date( $value ) {
        if ( ! $value ) return null;
        $ts = strtotime( $value );
        return $ts ? gmdate( 'Y-m-d', $ts ) : null;
    }

    private function money( $value ) { return is_numeric( $value ) ? round( (float) $value, 2 ) : null; }

    private function apply_normalized_payload( $report_id, $client_id, array $payload ) {
        $p = $this->wpdb->prefix;
        foreach ( array( 'tradelines','collections','inquiries','personal_information' ) as $table ) {
            $this->wpdb->delete( $p . 'creditos_' . $table, array( 'report_id' => $report_id, 'client_id' => $client_id ) );
        }
        foreach ( (array) ( $payload['tradelines'] ?? array() ) as $r ) {
            if ( empty( $r['creditor_name'] ) ) continue;
            $this->wpdb->insert( $p . 'creditos_tradelines', array(
                'report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),
                'account_number_masked'=>sanitize_text_field($r['account_number_masked']??''),'account_type'=>sanitize_text_field($r['account_type']??''),'opened_date'=>$this->clean_date($r['opened_date']??''),
                'status'=>sanitize_text_field($r['status']??''),'balance'=>$this->money($r['balance']??null),'credit_limit'=>$this->money($r['credit_limit']??null),'past_due'=>$this->money($r['past_due']??null),
                'payment_status'=>sanitize_text_field($r['payment_status']??''),'date_reported'=>$this->clean_date($r['date_reported']??''),'remarks'=>sanitize_textarea_field($r['remarks']??''),'responsibility'=>sanitize_text_field($r['responsibility']??''),'created_at'=>current_time('mysql')
            ) );
        }
        foreach ( (array) ( $payload['collections'] ?? array() ) as $r ) {
            if ( empty( $r['collector_name'] ) ) continue;
            $this->wpdb->insert( $p . 'creditos_collections', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'collector_name'=>sanitize_text_field($r['collector_name']),'original_creditor'=>sanitize_text_field($r['original_creditor']??''),'balance'=>$this->money($r['balance']??null),'assigned_date'=>$this->clean_date($r['assigned_date']??''),'status'=>sanitize_text_field($r['status']??''),'created_at'=>current_time('mysql')) );
        }
        foreach ( (array) ( $payload['inquiries'] ?? array() ) as $r ) {
            if ( empty( $r['creditor_name'] ) ) continue;
            $this->wpdb->insert( $p . 'creditos_inquiries', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),'inquiry_type'=>sanitize_key($r['inquiry_type']??''),'inquiry_date'=>$this->clean_date($r['inquiry_date']??''),'created_at'=>current_time('mysql')) );
        }
        foreach ( (array) ( $payload['personal_information'] ?? array() ) as $r ) {
            if ( empty( $r['info_type'] ) || empty( $r['info_value'] ) ) continue;
            $this->wpdb->insert( $p . 'creditos_personal_information', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'info_type'=>sanitize_key($r['info_type']),'info_value'=>sanitize_text_field($r['info_value']),'status'=>'reported','created_at'=>current_time('mysql')) );
        }
        $this->wpdb->update( $p . 'creditos_credit_reports', array( 'status'=>'ready_for_review','parser_status'=>'normalized','error_message'=>null,'updated_at'=>current_time('mysql') ), array( 'id'=>$report_id,'client_id'=>$client_id ) );
        return true;
    }

    private function report_payload( $report_id, $client_id ) {
        $p = $this->wpdb->prefix;
        $report = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT id,bureau,provider,report_date,imported_at,status,parser_status,source_format,source_filename,error_message FROM {$p}creditos_credit_reports WHERE id=%d AND client_id=%d LIMIT 1", $report_id, $client_id ), ARRAY_A );
        if ( ! $report ) return null;
        $report['tradelines'] = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$p}creditos_tradelines WHERE report_id=%d AND client_id=%d ORDER BY creditor_name", $report_id, $client_id ), ARRAY_A );
        $report['collections'] = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$p}creditos_collections WHERE report_id=%d AND client_id=%d ORDER BY collector_name", $report_id, $client_id ), ARRAY_A );
        $report['inquiries'] = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$p}creditos_inquiries WHERE report_id=%d AND client_id=%d ORDER BY inquiry_date DESC", $report_id, $client_id ), ARRAY_A );
        $report['personal_information'] = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$p}creditos_personal_information WHERE report_id=%d AND client_id=%d ORDER BY info_type,info_value", $report_id, $client_id ), ARRAY_A );
        return $report;
    }
}
