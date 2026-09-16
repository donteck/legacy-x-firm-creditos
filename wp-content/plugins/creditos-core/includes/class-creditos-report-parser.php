<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Phase 1B report parser.
 * Native PDF text extraction is always attempted before any OCR path.
 */
class CreditOS_Report_Parser {
    public function parse( $path, $format, $bureau = 'multi' ) {
        if ( ! $path || ! file_exists( $path ) ) return new WP_Error( 'creditos_parser_file_missing', 'The uploaded credit report file could not be found.' );
        $format = strtolower( (string) $format );
        if ( 'pdf' === $format ) return $this->parse_pdf( $path, $bureau );
        if ( 'csv' === $format ) return $this->parse_csv( $path, $bureau );
        return new WP_Error( 'creditos_parser_format', 'This report format does not use the CreditOS parser.' );
    }

    private function parse_pdf( $path, $bureau ) {
        $binary = $this->find_pdftotext();
        if ( ! $binary ) return new WP_Error( 'creditos_pdftotext_missing', 'PDF text extraction is not available on this server yet.' );
        $tmp = wp_tempnam( 'creditos-report.txt' );
        if ( ! $tmp ) return new WP_Error( 'creditos_parser_temp', 'CreditOS could not create a temporary parser file.' );
        $command = escapeshellarg( $binary ) . ' -layout -enc UTF-8 ' . escapeshellarg( $path ) . ' ' . escapeshellarg( $tmp ) . ' 2>&1';
        $output = array(); $code = 1;
        exec( $command, $output, $code );
        $text = ( 0 === $code && file_exists( $tmp ) ) ? (string) file_get_contents( $tmp ) : '';
        @unlink( $tmp );
        $text = $this->clean_text( $text );
        if ( strlen( $text ) < 120 ) return new WP_Error( 'creditos_pdf_needs_ocr', 'This PDF appears to be scanned or contains too little readable text. OCR review is required.' );
        $detected = $this->detect_bureau( $text, $bureau );
        return array(
            'status' => 'extracted',
            'bureau' => $detected,
            'text_length' => strlen( $text ),
            'page_count_hint' => max( 1, substr_count( $text, "\f" ) + 1 ),
            'text' => $text,
            'normalized' => $this->normalize_text( $text, $detected ),
        );
    }

    private function parse_csv( $path, $bureau ) {
        $handle = fopen( $path, 'r' );
        if ( ! $handle ) return new WP_Error( 'creditos_csv_open', 'CreditOS could not read the CSV report.' );
        $header = fgetcsv( $handle );
        if ( ! is_array( $header ) ) { fclose( $handle ); return new WP_Error( 'creditos_csv_header', 'The CSV report does not contain a readable header.' ); }
        $header = array_map( array( $this, 'key' ), $header ); $tradelines = array();
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row = array_pad( $row, count( $header ), '' ); $r = array_combine( $header, array_slice( $row, 0, count( $header ) ) );
            if ( ! $r ) continue; $creditor = $r['creditor_name'] ?? $r['creditor'] ?? $r['account_name'] ?? '';
            if ( ! $creditor ) continue;
            $tradelines[] = array('creditor_name'=>$creditor,'bureau'=>$r['bureau']??$bureau,'account_number_masked'=>$r['account_number_masked']??$r['account_number']??'','account_type'=>$r['account_type']??'','balance'=>$this->number($r['balance']??null),'credit_limit'=>$this->number($r['credit_limit']??$r['limit']??null),'past_due'=>$this->number($r['past_due']??null),'status'=>$r['status']??'','payment_status'=>$r['payment_status']??'','opened_date'=>$r['opened_date']??'','date_reported'=>$r['date_reported']??'','remarks'=>$r['remarks']??'');
        }
        fclose( $handle );
        return array( 'status'=>'normalized', 'bureau'=>$bureau, 'normalized'=>array('tradelines'=>$tradelines,'collections'=>array(),'inquiries'=>array(),'personal_information'=>array()) );
    }

    private function find_pdftotext() {
        foreach ( array('/usr/bin/pdftotext','/usr/local/bin/pdftotext') as $path ) if ( is_executable( $path ) ) return $path;
        return false;
    }

    private function clean_text( $text ) {
        $text = str_replace( "\0", '', (string) $text );
        $text = preg_replace( "/\r\n?|\r/", "\n", $text );
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        return trim( $text );
    }

    private function detect_bureau( $text, $fallback ) {
        /* Experian AnnualCreditReport PDFs may contain bureau names in explanatory copy;
           prefer strong document signatures before generic name matching. */
        if ( false !== stripos( $text, 'Annual Credit Report - Experian' ) || false !== stripos( $text, 'usa.experian.com/acr/' ) ) return 'experian';
        if ( false !== stripos( $text, 'Equifax' ) && false === stripos( $text, 'Experian' ) ) return 'equifax';
        if ( false !== stripos( $text, 'TransUnion' ) && false === stripos( $text, 'Experian' ) ) return 'transunion';
        return in_array( $fallback, array('experian','equifax','transunion','multi'), true ) ? $fallback : 'multi';
    }

    private function normalize_text( $text, $bureau ) {
        if ( 'experian' === $bureau || false !== stripos( $text, 'Annual Credit Report - Experian' ) ) return $this->normalize_experian( $text );
        return array('tradelines'=>array(),'collections'=>array(),'inquiries'=>array(),'personal_information'=>$this->parse_basic_personal($text,$bureau));
    }

    private function normalize_experian( $text ) {
        $tradelines = array();
        $personal = $this->parse_basic_personal( $text, 'experian' );
        /* Experian ACR account blocks consistently begin with Account Name and continue
           until the next Account Name or the major non-account sections. */
        $pattern = '/(?:^|\n)Account Name\s+(.+?)(?=\nAccount Name\s+|\n(?:Hard Inquiries|Soft Inquiries|Public Records|Consumer Statements|Personal Information)\b|\z)/si';
        if ( preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                $block = "Account Name " . trim( $m[1] );
                $name = $this->field( $block, 'Account Name', array('Account Number') );
                if ( ! $name ) continue;
                $status = $this->field( $block, 'Status', array('Status Updated') );
                $tradelines[] = array(
                    'creditor_name' => $name,
                    'bureau' => 'experian',
                    'account_number_masked' => $this->field($block,'Account Number',array('Account Type')),
                    'account_type' => $this->field($block,'Account Type',array('Responsibility')),
                    'responsibility' => $this->field($block,'Responsibility',array('Interest Type','Date Opened')),
                    'opened_date' => $this->field($block,'Date Opened',array('Status')),
                    'status' => $status,
                    'balance' => $this->money_field($block,'Balance',array('Balance Updated')),
                    'credit_limit' => $this->money_field($block,'Credit Limit',array('Highest Balance','Terms','Payment History')),
                    'past_due' => $this->past_due_from_status($status),
                    'payment_status' => $this->payment_status_from_block($block,$status),
                    'date_reported' => $this->field($block,'Balance Updated',array('Recent Payment','Monthly Payment')),
                    'remarks' => $this->remarks_from_block($block),
                );
            }
        }
        return array('tradelines'=>$tradelines,'collections'=>array(),'inquiries'=>$this->parse_experian_hard_inquiries($text),'personal_information'=>$personal);
    }

    private function field( $block, $label, array $next_labels = array() ) {
        $next = $next_labels ? '(?=\s*(?:' . implode('|', array_map(function($v){return preg_quote($v,'/');},$next_labels)) . ')\b|\n|\z)' : '(?=\n|\z)';
        if ( preg_match('/(?:^|\n)'.preg_quote($label,'/').'\s+(.+?)'.$next.'/si',$block,$m) ) return trim(preg_replace('/\s+/',' ', $m[1]));
        return '';
    }

    private function money_field( $block, $label, array $next ) {
        $v = $this->field($block,$label,$next); if ( '' === $v || '-' === trim($v) ) return null; return $this->number($v);
    }

    private function past_due_from_status( $status ) {
        if ( preg_match('/\$([0-9,]+(?:\.\d{2})?)\s+past due/i',(string)$status,$m) ) return $this->number($m[1]);
        return null;
    }

    private function payment_status_from_block( $block, $status ) {
        if ( stripos($status,'charged off') !== false ) return 'Charge Off';
        if ( preg_match('/\b(30|60|90|120|150|180) days past due\b/i',$block,$m) ) return $m[1].' Days Past Due';
        if ( stripos($status,'paid') !== false && stripos($status,'closed') !== false ) return 'Paid / Closed';
        if ( stripos($status,'open') !== false ) return 'Open';
        return $status;
    }

    private function remarks_from_block( $block ) {
        $remarks = array();
        if ( preg_match('/On Record Until\s+([^\n]+)/i',$block,$m) ) $remarks[] = 'On record until '.trim($m[1]);
        if ( preg_match('/This account is scheduled to continue on record until\s+([^\.\n]+)[\.\n]/i',$block,$m) ) $remarks[] = 'Scheduled through '.trim($m[1]);
        return implode('; ',array_unique($remarks));
    }

    private function parse_basic_personal( $text, $bureau ) {
        $out = array();
        if ( preg_match('/Prepared For\s*\n?\s*([^\n]{3,80})/i',$text,$m) ) $out[] = array('info_type'=>'name','info_value'=>trim($m[1]),'bureau'=>$bureau);
        if ( preg_match('/Date Generated\s+([A-Za-z]{3,9}\s+\d{1,2},\s+\d{4})/i',$text,$m) ) $out[] = array('info_type'=>'report_date','info_value'=>trim($m[1]),'bureau'=>$bureau);
        return $out;
    }

    private function parse_experian_hard_inquiries( $text ) {
        /* The summary count is authoritative for whether this section should be parsed. */
        if ( preg_match('/\b0\s+Hard Inquiries\b/i',$text) ) return array();
        return array(); // detailed inquiry adapter follows after a non-zero sample is QA'd.
    }

    public function safe_excerpt( $text, $limit = 800 ) {
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/','***-**-****',(string)$text);
        $text = preg_replace('/\b\d{9,16}\b/','********',$text);
        return mb_substr($text,0,$limit);
    }

    private function key( $value ) { return sanitize_key(str_replace(array(' ','-'),'_',strtolower(trim((string)$value)))); }
    private function number( $value ) { if ( null === $value || '' === $value ) return null; $v=preg_replace('/[^0-9.\-]/','',(string)$value); return is_numeric($v)?(float)$v:null; }
}
