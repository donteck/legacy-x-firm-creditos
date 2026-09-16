<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Wires successful /reports/import requests into the Phase 1B parser.
 * Kept separate from the importer so the upload contract remains stable.
 */
class CreditOS_Report_Processing {
    private $repository;
    private $wpdb;
    private $parser;

    public function __construct( CreditOS_Repository $repository ) {
        global $wpdb;
        $this->repository = $repository;
        $this->wpdb = $wpdb;
        $this->parser = new CreditOS_Report_Parser();
        add_filter( 'rest_request_after_callbacks', array( $this, 'process_after_import' ), 10, 3 );
    }

    public function process_after_import( $response, $handler, $request ) {
        if ( '/creditos/v1/reports/import' !== $request->get_route() || 'POST' !== $request->get_method() ) return $response;
        if ( is_wp_error( $response ) ) return $response;
        $server = rest_get_server();
        $data = $server->response_to_data( $response, false );
        $report_id = absint( $data['report_id'] ?? 0 );
        if ( ! $report_id ) return $response;

        $report = $this->wpdb->get_row( $this->wpdb->prepare(
            "SELECT id,client_id,bureau,source_attachment_id,source_format FROM {$this->wpdb->prefix}creditos_credit_reports WHERE id=%d LIMIT 1",
            $report_id
        ), ARRAY_A );
        if ( ! $report || ! in_array( $report['source_format'], array( 'pdf', 'csv' ), true ) ) return $response;

        $this->set_status( $report_id, 'processing', null );
        $path = get_attached_file( absint( $report['source_attachment_id'] ) );
        $parsed = $this->parser->parse( $path, $report['source_format'], $report['bureau'] );

        if ( is_wp_error( $parsed ) ) {
            $code = $parsed->get_error_code();
            $status = 'creditos_pdf_needs_ocr' === $code ? 'needs_ocr' : 'needs_review';
            $this->set_status( $report_id, $status, $parsed->get_error_message() );
            $this->repository->audit( get_current_user_id(), absint( $report['client_id'] ), 'credit_report_processing_review_required', 'credit_report', $report_id, array( 'reason'=>$code ) );
            return $this->refresh_response( $response, $report_id, absint( $report['client_id'] ) );
        }

        $this->set_status( $report_id, 'extracting', null );
        if ( ! empty( $parsed['bureau'] ) ) {
            $this->wpdb->update( $this->wpdb->prefix . 'creditos_credit_reports', array( 'bureau'=>sanitize_key( $parsed['bureau'] ) ), array( 'id'=>$report_id ) );
        }

        $normalized = is_array( $parsed['normalized'] ?? null ) ? $parsed['normalized'] : array();
        $this->set_status( $report_id, 'normalizing', null );
        $this->store_normalized( $report_id, absint( $report['client_id'] ), $normalized );
        $this->set_status( $report_id, 'ready_for_review', null, 'ready_for_review' );
        $this->repository->audit( get_current_user_id(), absint( $report['client_id'] ), 'credit_report_parser_completed', 'credit_report', $report_id, array( 'format'=>$report['source_format'], 'bureau'=>$parsed['bureau'] ?? $report['bureau'] ) );
        return $this->refresh_response( $response, $report_id, absint( $report['client_id'] ) );
    }

    private function set_status( $report_id, $parser_status, $error = null, $status = null ) {
        $values = array( 'parser_status'=>$parser_status, 'error_message'=>$error, 'updated_at'=>current_time( 'mysql' ) );
        if ( $status ) $values['status'] = $status;
        $this->wpdb->update( $this->wpdb->prefix . 'creditos_credit_reports', $values, array( 'id'=>$report_id ) );
    }

    private function store_normalized( $report_id, $client_id, array $payload ) {
        $p = $this->wpdb->prefix;
        foreach ( array( 'tradelines','collections','inquiries','personal_information' ) as $table ) {
            $this->wpdb->delete( $p . 'creditos_' . $table, array( 'report_id'=>$report_id, 'client_id'=>$client_id ) );
        }
        foreach ( (array) ( $payload['tradelines'] ?? array() ) as $r ) {
            if ( empty( $r['creditor_name'] ) ) continue;
            $this->wpdb->insert( $p.'creditos_tradelines', array(
                'report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),
                'account_number_masked'=>sanitize_text_field($r['account_number_masked']??''),'account_type'=>sanitize_text_field($r['account_type']??''),
                'opened_date'=>$this->date($r['opened_date']??''),'status'=>sanitize_text_field($r['status']??''),'balance'=>$this->money($r['balance']??null),
                'credit_limit'=>$this->money($r['credit_limit']??null),'past_due'=>$this->money($r['past_due']??null),'payment_status'=>sanitize_text_field($r['payment_status']??''),
                'date_reported'=>$this->date($r['date_reported']??''),'remarks'=>sanitize_textarea_field($r['remarks']??''),'responsibility'=>sanitize_text_field($r['responsibility']??''),'created_at'=>current_time('mysql')
            ) );
        }
        foreach ( (array) ( $payload['collections'] ?? array() ) as $r ) {
            if ( empty( $r['collector_name'] ) ) continue;
            $this->wpdb->insert( $p.'creditos_collections', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'collector_name'=>sanitize_text_field($r['collector_name']),'original_creditor'=>sanitize_text_field($r['original_creditor']??''),'balance'=>$this->money($r['balance']??null),'assigned_date'=>$this->date($r['assigned_date']??''),'status'=>sanitize_text_field($r['status']??''),'created_at'=>current_time('mysql')) );
        }
        foreach ( (array) ( $payload['inquiries'] ?? array() ) as $r ) {
            if ( empty( $r['creditor_name'] ) ) continue;
            $this->wpdb->insert( $p.'creditos_inquiries', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),'inquiry_type'=>sanitize_key($r['inquiry_type']??''),'inquiry_date'=>$this->date($r['inquiry_date']??''),'created_at'=>current_time('mysql')) );
        }
        foreach ( (array) ( $payload['personal_information'] ?? array() ) as $r ) {
            if ( empty( $r['info_type'] ) || empty( $r['info_value'] ) ) continue;
            $this->wpdb->insert( $p.'creditos_personal_information', array('report_id'=>$report_id,'client_id'=>$client_id,'bureau'=>sanitize_key($r['bureau']??''),'info_type'=>sanitize_key($r['info_type']),'info_value'=>sanitize_text_field($r['info_value']),'status'=>'reported','created_at'=>current_time('mysql')) );
        }
    }

    private function refresh_response( $response, $report_id, $client_id ) {
        $p = $this->wpdb->prefix;
        $report = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT id,bureau,provider,report_date,imported_at,status,parser_status,source_format,source_filename,error_message FROM {$p}creditos_credit_reports WHERE id=%d AND client_id=%d LIMIT 1", $report_id, $client_id ), ARRAY_A );
        if ( ! $report ) return $response;
        $data = array( 'success'=>true, 'report_id'=>$report_id, 'report'=>$report );
        return rest_ensure_response( $data );
    }

    private function date( $value ) { if ( ! $value ) return null; $ts=strtotime($value); return $ts ? gmdate('Y-m-d',$ts) : null; }
    private function money( $value ) { return is_numeric($value) ? round((float)$value,2) : null; }
}
