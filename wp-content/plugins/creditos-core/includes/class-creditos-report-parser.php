<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Phase 1B report parser foundation.
 * Native PDF text extraction is intentionally attempted before any OCR path.
 */
class CreditOS_Report_Parser {
    public function parse( $path, $format, $bureau = 'multi' ) {
        if ( ! $path || ! file_exists( $path ) ) {
            return new WP_Error( 'creditos_parser_file_missing', 'The uploaded credit report file could not be found.' );
        }

        $format = strtolower( (string) $format );
        if ( 'pdf' === $format ) return $this->parse_pdf( $path, $bureau );
        if ( 'csv' === $format ) return $this->parse_csv( $path, $bureau );
        return new WP_Error( 'creditos_parser_format', 'This report format does not use the CreditOS parser.' );
    }

    private function parse_pdf( $path, $bureau ) {
        $binary = $this->find_pdftotext();
        if ( ! $binary ) {
            return new WP_Error( 'creditos_pdftotext_missing', 'PDF text extraction is not available on this server yet.' );
        }

        $tmp = wp_tempnam( 'creditos-report.txt' );
        if ( ! $tmp ) return new WP_Error( 'creditos_parser_temp', 'CreditOS could not create a temporary parser file.' );

        $command = escapeshellarg( $binary ) . ' -layout -enc UTF-8 ' . escapeshellarg( $path ) . ' ' . escapeshellarg( $tmp ) . ' 2>&1';
        $output = array(); $code = 1;
        exec( $command, $output, $code );
        $text = ( 0 === $code && file_exists( $tmp ) ) ? (string) file_get_contents( $tmp ) : '';
        @unlink( $tmp );

        $text = $this->clean_text( $text );
        if ( strlen( $text ) < 120 ) {
            return new WP_Error( 'creditos_pdf_needs_ocr', 'This PDF appears to be scanned or contains too little readable text. OCR review is required.' );
        }

        return array(
            'status' => 'extracted',
            'bureau' => $this->detect_bureau( $text, $bureau ),
            'text_length' => strlen( $text ),
            'page_count_hint' => max( 1, substr_count( $text, "\f" ) + 1 ),
            'text' => $text,
            'normalized' => $this->normalize_text( $text, $bureau ),
        );
    }

    private function parse_csv( $path, $bureau ) {
        $handle = fopen( $path, 'r' );
        if ( ! $handle ) return new WP_Error( 'creditos_csv_open', 'CreditOS could not read the CSV report.' );
        $header = fgetcsv( $handle );
        if ( ! is_array( $header ) ) { fclose( $handle ); return new WP_Error( 'creditos_csv_header', 'The CSV report does not contain a readable header.' ); }
        $header = array_map( array( $this, 'key' ), $header );
        $tradelines = array();
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row = array_pad( $row, count( $header ), '' );
            $r = array_combine( $header, array_slice( $row, 0, count( $header ) ) );
            if ( ! $r ) continue;
            $creditor = $r['creditor_name'] ?? $r['creditor'] ?? $r['account_name'] ?? '';
            if ( ! $creditor ) continue;
            $tradelines[] = array(
                'creditor_name' => $creditor,
                'bureau' => $r['bureau'] ?? $bureau,
                'account_number_masked' => $r['account_number_masked'] ?? $r['account_number'] ?? '',
                'account_type' => $r['account_type'] ?? '',
                'balance' => $this->number( $r['balance'] ?? null ),
                'credit_limit' => $this->number( $r['credit_limit'] ?? $r['limit'] ?? null ),
                'past_due' => $this->number( $r['past_due'] ?? null ),
                'status' => $r['status'] ?? '',
                'payment_status' => $r['payment_status'] ?? '',
                'opened_date' => $r['opened_date'] ?? '',
                'date_reported' => $r['date_reported'] ?? '',
                'remarks' => $r['remarks'] ?? '',
            );
        }
        fclose( $handle );
        return array( 'status'=>'normalized', 'bureau'=>$bureau, 'normalized'=>array( 'tradelines'=>$tradelines, 'collections'=>array(), 'inquiries'=>array(), 'personal_information'=>array() ) );
    }

    private function find_pdftotext() {
        $candidates = array( '/usr/bin/pdftotext', '/usr/local/bin/pdftotext' );
        foreach ( $candidates as $path ) if ( is_executable( $path ) ) return $path;
        return false;
    }

    private function clean_text( $text ) {
        $text = str_replace( "\0", '', (string) $text );
        $text = preg_replace( "/\r\n?|\n/", "\n", $text );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        return trim( $text );
    }

    private function detect_bureau( $text, $fallback ) {
        $found = array();
        foreach ( array( 'experian'=>'experian', 'equifax'=>'equifax', 'transunion'=>'transunion' ) as $key=>$needle ) {
            if ( false !== stripos( $text, $needle ) ) $found[] = $key;
        }
        return 1 === count( $found ) ? $found[0] : ( count( $found ) > 1 ? 'multi' : $fallback );
    }

    /**
     * Conservative first-pass normalization. We do not invent tradelines from
     * ambiguous PDF text. Detailed bureau adapters are added after sample-report QA.
     */
    private function normalize_text( $text, $bureau ) {
        $personal = array();
        if ( preg_match( '/(?:consumer|prepared for|report for)\s*:?\s*([A-Z][A-Za-z .\'-]{3,80})/i', $text, $m ) ) {
            $personal[] = array( 'info_type'=>'name', 'info_value'=>trim( $m[1] ), 'bureau'=>$this->detect_bureau( $text, $bureau ) );
        }
        return array( 'tradelines'=>array(), 'collections'=>array(), 'inquiries'=>array(), 'personal_information'=>$personal );
    }

    public function safe_excerpt( $text, $limit = 800 ) {
        $text = preg_replace( '/\b\d{3}-\d{2}-\d{4}\b/', '***-**-****', (string) $text );
        $text = preg_replace( '/\b\d{9,16}\b/', '********', $text );
        return mb_substr( $text, 0, $limit );
    }

    private function key( $value ) { return sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( trim( (string) $value ) ) ) ); }
    private function number( $value ) { if ( null === $value || '' === $value ) return null; $v = preg_replace( '/[^0-9.\-]/', '', (string) $value ); return is_numeric( $v ) ? (float) $v : null; }
}
