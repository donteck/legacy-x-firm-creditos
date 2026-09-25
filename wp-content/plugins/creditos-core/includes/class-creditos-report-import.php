<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CreditOS_Report_Import {
    private $repository; private $wpdb;
    public function __construct( CreditOS_Repository $repository ) { global $wpdb; $this->repository=$repository; $this->wpdb=$wpdb; add_action('init',array($this,'maybe_install_schema'),5); add_action('rest_api_init',array($this,'register_routes')); }

    public function maybe_install_schema(){
        $this->ensure_phase1b_tradeline_columns();
        if(get_option('creditos_reports_schema_version')===CREDITOS_CORE_VERSION)return;
        require_once ABSPATH.'wp-admin/includes/upgrade.php'; $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        $tables=array(
        "CREATE TABLE {$p}creditos_credit_reports (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,client_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NOT NULL DEFAULT 'multi',provider VARCHAR(80) NOT NULL DEFAULT 'manual_upload',report_date DATE NULL,imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,status VARCHAR(30) NOT NULL DEFAULT 'uploaded',parser_status VARCHAR(30) NOT NULL DEFAULT 'pending',source_attachment_id BIGINT UNSIGNED NULL,source_format VARCHAR(20) NULL,source_filename VARCHAR(255) NULL,error_message TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY client_id(client_id),KEY status(status),KEY parser_status(parser_status)) $c;",
        "CREATE TABLE {$p}creditos_credit_report_sources (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,report_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NULL,source_type VARCHAR(40) NOT NULL DEFAULT 'upload',provider VARCHAR(80) NULL,raw_reference VARCHAR(255) NULL,checksum VARCHAR(128) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY report_id(report_id)) $c;",
        "CREATE TABLE {$p}creditos_tradelines (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,report_id BIGINT UNSIGNED NOT NULL,client_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NULL,creditor_name VARCHAR(190) NOT NULL,account_number_masked VARCHAR(80) NULL,account_type VARCHAR(80) NULL,opened_date DATE NULL,status VARCHAR(100) NULL,status_updated DATE NULL,balance DECIMAL(14,2) NULL,credit_limit DECIMAL(14,2) NULL,past_due DECIMAL(14,2) NULL,payment_status VARCHAR(120) NULL,balance_updated DATE NULL,date_reported DATE NULL,remarks TEXT NULL,responsibility VARCHAR(80) NULL,review_status VARCHAR(30) NOT NULL DEFAULT 'needs_review',reviewer_notes TEXT NULL,discrepancy_type VARCHAR(50) NULL,discrepancy_field VARCHAR(50) NULL,discrepancy_details TEXT NULL,evidence_status VARCHAR(30) NOT NULL DEFAULT 'not_requested',evidence_notes TEXT NULL,reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY report_id(report_id),KEY client_id(client_id),KEY bureau(bureau)) $c;",
        "CREATE TABLE {$p}creditos_collections (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,report_id BIGINT UNSIGNED NOT NULL,client_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NULL,collector_name VARCHAR(190) NOT NULL,original_creditor VARCHAR(190) NULL,balance DECIMAL(14,2) NULL,assigned_date DATE NULL,status VARCHAR(100) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY report_id(report_id),KEY client_id(client_id)) $c;",
        "CREATE TABLE {$p}creditos_inquiries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,report_id BIGINT UNSIGNED NOT NULL,client_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NULL,creditor_name VARCHAR(190) NOT NULL,inquiry_type VARCHAR(40) NULL,inquiry_date DATE NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY report_id(report_id),KEY client_id(client_id)) $c;",
        "CREATE TABLE {$p}creditos_personal_information (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,report_id BIGINT UNSIGNED NOT NULL,client_id BIGINT UNSIGNED NOT NULL,bureau VARCHAR(40) NULL,info_type VARCHAR(40) NOT NULL,info_value VARCHAR(255) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'reported',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY report_id(report_id),KEY client_id(client_id),KEY info_type(info_type)) $c;"
        ); foreach($tables as$sql)dbDelta($sql); update_option('creditos_reports_schema_version',CREDITOS_CORE_VERSION);
    }

    private function ensure_phase1b_tradeline_columns(){
        $table=$this->wpdb->prefix.'creditos_tradelines';
        $required=array(
            'status_updated'=>"DATE NULL AFTER status",
            'balance_updated'=>"DATE NULL AFTER payment_status",
            'review_status'=>"VARCHAR(30) NOT NULL DEFAULT 'needs_review' AFTER responsibility",
            'reviewer_notes'=>"TEXT NULL AFTER review_status",
            'discrepancy_type'=>"VARCHAR(50) NULL AFTER reviewer_notes",
            'discrepancy_field'=>"VARCHAR(50) NULL AFTER discrepancy_type",
            'discrepancy_details'=>"TEXT NULL AFTER discrepancy_field",
            'evidence_status'=>"VARCHAR(30) NOT NULL DEFAULT 'not_requested' AFTER discrepancy_details",
            'evidence_notes'=>"TEXT NULL AFTER evidence_status",
            'reviewed_by'=>"BIGINT UNSIGNED NULL AFTER evidence_notes",
            'reviewed_at'=>"DATETIME NULL AFTER reviewed_by"
        );
        foreach($required as $column=>$definition){
            $exists=$this->wpdb->get_var($this->wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s",$column));
            if(!$exists)$this->wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    public function register_routes(){
        register_rest_route('creditos/v1','/reports',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'list_reports'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/import',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'import_report'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/(?P<id>\d+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_report'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/(?P<id>\d+)/normalized',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'save_normalized'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/(?P<id>\d+)/reprocess',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'reprocess_report'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/(?P<id>\d+)/tradelines/(?P<tradeline_id>\d+)/review',array('methods'=>WP_REST_Server::EDITABLE,'callback'=>array($this,'review_tradeline'),'permission_callback'=>array($this,'logged_in')));
        register_rest_route('creditos/v1','/reports/diagnostics',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'diagnostics'),'permission_callback'=>array($this,'staff_only')));
    }
    public function logged_in(){return is_user_logged_in();}
    public function staff_only(){return is_user_logged_in()&&(current_user_can('manage_options')||current_user_can('creditos_manage_clients'));}
    private function current_client(){return $this->repository->get_or_create_client_for_user(get_current_user_id());}
    private function consent_is_valid($id){$t=$this->wpdb->prefix.'creditos_onboarding';return(bool)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$t} WHERE client_id=%d AND consented_at IS NOT NULL LIMIT 1",$id));}

    public function list_reports(){ $c=$this->current_client(); if(!$c)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404)); $t=$this->wpdb->prefix.'creditos_credit_reports'; $rows=$this->wpdb->get_results($this->wpdb->prepare("SELECT id,bureau,provider,report_date,imported_at,status,parser_status,source_format,source_filename,error_message FROM {$t} WHERE client_id=%d ORDER BY imported_at DESC,id DESC",$c->id),ARRAY_A); return rest_ensure_response(array('reports'=>$rows)); }

    public function import_report(WP_REST_Request $request){
        $c=$this->current_client(); if(!$c)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404)); if(!$this->consent_is_valid($c->id))return new WP_Error('creditos_report_consent_required','Complete CreditOS onboarding and consent before importing a credit report.',array('status'=>403));
        $files=$request->get_file_params(); if(empty($files['report']['tmp_name']))return new WP_Error('creditos_report_required','Choose a credit report file to import.',array('status'=>400)); $file=$files['report']; if(!empty($file['size'])&&(int)$file['size']>25*MB_IN_BYTES)return new WP_Error('creditos_report_too_large','Credit report files must be 25 MB or smaller.',array('status'=>400));
        $allowed=array('pdf'=>'application/pdf','json'=>'application/json','csv'=>'text/csv'); $check=wp_check_filetype_and_ext($file['tmp_name'],$file['name'],$allowed); $ext=strtolower($check['ext']?:pathinfo($file['name'],PATHINFO_EXTENSION)); if(!isset($allowed[$ext]))return new WP_Error('creditos_report_type','Supported report formats are PDF, JSON, and CSV.',array('status'=>400));
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';
        $aid=media_handle_upload('report',0,array('post_title'=>sanitize_file_name($file['name']),'post_author'=>get_current_user_id()),array('test_form'=>false,'mimes'=>$allowed)); if(is_wp_error($aid))return $aid; update_post_meta($aid,'_creditos_private','1');
        $bureau=sanitize_key($request->get_param('bureau')?:'multi'); if(!in_array($bureau,array('experian','equifax','transunion','multi'),true))$bureau='multi'; $date=sanitize_text_field($request->get_param('report_date')?:''); $now=current_time('mysql'); $t=$this->wpdb->prefix.'creditos_credit_reports';
        $this->wpdb->insert($t,array('client_id'=>absint($c->id),'bureau'=>$bureau,'provider'=>'manual_upload','report_date'=>$date?:null,'imported_at'=>$now,'status'=>'processing','parser_status'=>'processing','source_attachment_id'=>absint($aid),'source_format'=>$ext,'source_filename'=>sanitize_file_name($file['name']),'created_at'=>$now,'updated_at'=>$now)); $rid=absint($this->wpdb->insert_id); $path=get_attached_file($aid);
        $this->wpdb->insert($this->wpdb->prefix.'creditos_credit_report_sources',array('report_id'=>$rid,'bureau'=>$bureau,'source_type'=>'upload','provider'=>'manual_upload','raw_reference'=>(string)$aid,'checksum'=>$path&&file_exists($path)?hash_file('sha256',$path):null,'created_at'=>$now)); $this->repository->audit(get_current_user_id(),$c->id,'credit_report_uploaded','credit_report',$rid,array('bureau'=>$bureau,'format'=>$ext));
        $this->process_report($rid,$c->id,$path,$ext,$bureau);
        return rest_ensure_response(array('success'=>true,'report_id'=>$rid,'report'=>$this->report_payload($rid,$c->id)));
    }

    private function process_report($rid,$cid,$path,$ext,$bureau){
        $t=$this->wpdb->prefix.'creditos_credit_reports'; $this->wpdb->update($t,array('status'=>'processing','parser_status'=>'processing','error_message'=>null,'updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));
        try{
            if(!$path||!file_exists($path)){ $this->mark_failed($rid,$cid,'Uploaded source file is unavailable on the server.'); return false; }
            if('json'===$ext){$payload=json_decode(file_get_contents($path),true); if(!is_array($payload)){ $this->mark_failed($rid,$cid,'JSON could not be parsed.'); return false; } return $this->apply_normalized_payload($rid,$cid,$payload);}
            $parser=new CreditOS_Report_Parser(); $parsed=$parser->parse($path,$ext,$bureau);
            if(is_wp_error($parsed)){ $code=$parsed->get_error_code(); $status=('creditos_pdf_needs_ocr'===$code)?'needs_ocr':'failed'; $this->wpdb->update($t,array('status'=>'needs_review','parser_status'=>$status,'error_message'=>$parsed->get_error_message(),'updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid)); $this->repository->audit(get_current_user_id(),$cid,'credit_report_parse_failed','credit_report',$rid,array('code'=>$code)); return false; }
            if(!empty($parsed['bureau']))$this->wpdb->update($t,array('bureau'=>sanitize_key($parsed['bureau'])),array('id'=>$rid,'client_id'=>$cid));
            $payload=is_array($parsed['normalized']??null)?$parsed['normalized']:array(); $result=$this->apply_normalized_payload($rid,$cid,$payload); if($result)$this->repository->audit(get_current_user_id(),$cid,'credit_report_parsed','credit_report',$rid,array('bureau'=>$parsed['bureau']??$bureau,'text_length'=>$parsed['text_length']??0)); return $result;
        }catch(Throwable $e){
            $safe_class = sanitize_text_field(get_class($e));
            $safe_message = sanitize_text_field($e->getMessage());
            if (strlen($safe_message) > 240) $safe_message = substr($safe_message, 0, 240) . '…';
            $this->mark_failed($rid,$cid,'CreditOS parser exception ['.$safe_class.']: '.$safe_message);
            $this->repository->audit(get_current_user_id(),$cid,'credit_report_processing_exception','credit_report',$rid,array('exception_class'=>$safe_class));
            return false;
        }
    }

    private function mark_failed($rid,$cid,$message){$this->wpdb->update($this->wpdb->prefix.'creditos_credit_reports',array('status'=>'needs_review','parser_status'=>'failed','error_message'=>$message,'updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));}

    public function reprocess_report(WP_REST_Request $request){$c=$this->current_client();if(!$c)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404));$rid=absint($request['id']);$p=$this->wpdb->prefix;$row=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$p}creditos_credit_reports WHERE id=%d AND client_id=%d LIMIT 1",$rid,$c->id),ARRAY_A);if(!$row)return new WP_Error('creditos_report_not_found','Credit report not found.',array('status'=>404));$path=get_attached_file(absint($row['source_attachment_id']));$ok=$this->process_report($rid,$c->id,$path,$row['source_format'],$row['bureau']);return rest_ensure_response(array('success'=>(bool)$ok,'report'=>$this->report_payload($rid,$c->id)));}

    public function review_tradeline(WP_REST_Request $request){
        $client=$this->current_client(); if(!$client)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404));
        $rid=absint($request['id']); $tid=absint($request['tradeline_id']); $data=$request->get_json_params();
        $allowed=array('verified','needs_review','potential_inaccuracy','missing_evidence','ignore'); $status=sanitize_key($data['review_status']??'needs_review');
        if(!in_array($status,$allowed,true))return new WP_Error('creditos_review_status_invalid','Choose a valid review status.',array('status'=>400));
        $table=$this->wpdb->prefix.'creditos_tradelines'; $exists=$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE id=%d AND report_id=%d AND client_id=%d LIMIT 1",$tid,$rid,$client->id));
        if(!$exists)return new WP_Error('creditos_tradeline_not_found','Tradeline not found.',array('status'=>404));
        $allowed_types=array('','identity','account_status','balance','credit_limit','payment_history','dates','ownership','duplicate','other');
        $dtype=sanitize_key($data['discrepancy_type']??''); if(!in_array($dtype,$allowed_types,true))$dtype='other';
        $allowed_evidence=array('not_requested','needed','requested','received','reviewed');
        $evidence_status=sanitize_key($data['evidence_status']??'not_requested'); if(!in_array($evidence_status,$allowed_evidence,true))$evidence_status='not_requested';
        $allowed_fields=array('','creditor_name','account_number_masked','account_type','responsibility','opened_date','status','status_updated','balance','credit_limit','past_due','payment_status','balance_updated','remarks');
        $dfield=sanitize_key($data['discrepancy_field']??''); if(!in_array($dfield,$allowed_fields,true))$dfield='';
        $ok=$this->wpdb->update($table,array('review_status'=>$status,'reviewer_notes'=>sanitize_textarea_field($data['reviewer_notes']??''),'discrepancy_type'=>$dtype?:null,'discrepancy_field'=>$dfield?:null,'discrepancy_details'=>sanitize_textarea_field($data['discrepancy_details']??''),'evidence_status'=>$evidence_status,'evidence_notes'=>sanitize_textarea_field($data['evidence_notes']??''),'reviewed_by'=>get_current_user_id(),'reviewed_at'=>current_time('mysql')),array('id'=>$tid,'report_id'=>$rid,'client_id'=>$client->id));
        if(false===$ok)return new WP_Error('creditos_review_save_failed','The account review could not be saved.',array('status'=>500));
        $this->repository->audit(get_current_user_id(),$client->id,'tradeline_review_saved','tradeline',$tid,array('report_id'=>$rid,'review_status'=>$status,'discrepancy_type'=>$dtype,'discrepancy_field'=>$dfield,'evidence_status'=>$evidence_status));
        return rest_ensure_response(array('success'=>true,'review_status'=>$status));
    }

    public function diagnostics(){ $disabled=array_map('trim',explode(',',(string)ini_get('disable_functions'))); return rest_ensure_response(array('php'=>PHP_VERSION,'exec_available'=>function_exists('exec')&&!in_array('exec',$disabled,true),'pdftotext_usr_bin'=>is_executable('/usr/bin/pdftotext'),'pdftotext_usr_local'=>is_executable('/usr/local/bin/pdftotext'),'upload_dir_writable'=>wp_is_writable(wp_upload_dir()['basedir']),'core_version'=>defined('CREDITOS_CORE_VERSION')?CREDITOS_CORE_VERSION:'')); }

    public function get_report(WP_REST_Request $request){$c=$this->current_client();if(!$c)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404));$payload=$this->report_payload(absint($request['id']),$c->id);if(!$payload)return new WP_Error('creditos_report_not_found','Credit report not found.',array('status'=>404));return rest_ensure_response($payload);}
    public function save_normalized(WP_REST_Request $request){$c=$this->current_client();if(!$c)return new WP_Error('creditos_client_missing','CreditOS client profile could not be loaded.',array('status'=>404));$rid=absint($request['id']);if(!$this->report_payload($rid,$c->id))return new WP_Error('creditos_report_not_found','Credit report not found.',array('status'=>404));$payload=$request->get_json_params();$result=$this->apply_normalized_payload($rid,$c->id,is_array($payload)?$payload:array());if(is_wp_error($result))return$result;$this->repository->audit(get_current_user_id(),$c->id,'credit_report_normalized','credit_report',$rid);return rest_ensure_response(array('success'=>true,'report'=>$this->report_payload($rid,$c->id)));}
    private function clean_date($v){if(!$v)return null;$ts=strtotime($v);return$ts?gmdate('Y-m-d',$ts):null;} private function money($v){return is_numeric($v)?round((float)$v,2):null;}

    private function apply_normalized_payload($rid,$cid,array$payload){$p=$this->wpdb->prefix;
        // Validate the new payload before deleting any previously normalized records.
        $incoming=0; foreach(array('tradelines','collections','inquiries','personal_information')as$table)$incoming+=count((array)($payload[$table]??array()));
        if($incoming<1){$this->wpdb->update($p.'creditos_credit_reports',array('status'=>'needs_review','parser_status'=>'needs_review','error_message'=>'No structured credit records were extracted. Existing normalized records were preserved.','updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));return false;}
        $this->wpdb->query('START TRANSACTION');
        $write_failed=false;
        foreach(array('tradelines','collections','inquiries','personal_information')as$table)$this->wpdb->delete($p.'creditos_'.$table,array('report_id'=>$rid,'client_id'=>$cid));
        foreach((array)($payload['tradelines']??array())as$r){if(empty($r['creditor_name']))continue;if(false===$this->wpdb->insert($p.'creditos_tradelines',array('report_id'=>$rid,'client_id'=>$cid,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),'account_number_masked'=>sanitize_text_field($r['account_number_masked']??''),'account_type'=>sanitize_text_field($r['account_type']??''),'opened_date'=>$this->clean_date($r['opened_date']??''),'status'=>sanitize_text_field($r['status']??''),'status_updated'=>$this->clean_date($r['status_updated']??''),'balance'=>$this->money($r['balance']??null),'credit_limit'=>$this->money($r['credit_limit']??null),'past_due'=>$this->money($r['past_due']??null),'payment_status'=>sanitize_text_field($r['payment_status']??''),'balance_updated'=>$this->clean_date($r['balance_updated']??''),'date_reported'=>$this->clean_date($r['date_reported']??''),'remarks'=>sanitize_textarea_field($r['remarks']??''),'responsibility'=>sanitize_text_field($r['responsibility']??''),'created_at'=>current_time('mysql'))))$write_failed=true;}
        foreach((array)($payload['collections']??array())as$r){if(empty($r['collector_name']))continue;$this->wpdb->insert($p.'creditos_collections',array('report_id'=>$rid,'client_id'=>$cid,'bureau'=>sanitize_key($r['bureau']??''),'collector_name'=>sanitize_text_field($r['collector_name']),'original_creditor'=>sanitize_text_field($r['original_creditor']??''),'balance'=>$this->money($r['balance']??null),'assigned_date'=>$this->clean_date($r['assigned_date']??''),'status'=>sanitize_text_field($r['status']??''),'created_at'=>current_time('mysql')));}
        foreach((array)($payload['inquiries']??array())as$r){if(empty($r['creditor_name']))continue;$this->wpdb->insert($p.'creditos_inquiries',array('report_id'=>$rid,'client_id'=>$cid,'bureau'=>sanitize_key($r['bureau']??''),'creditor_name'=>sanitize_text_field($r['creditor_name']),'inquiry_type'=>sanitize_key($r['inquiry_type']??''),'inquiry_date'=>$this->clean_date($r['inquiry_date']??''),'created_at'=>current_time('mysql')));}
        foreach((array)($payload['personal_information']??array())as$r){if(empty($r['info_type'])||empty($r['info_value']))continue;$this->wpdb->insert($p.'creditos_personal_information',array('report_id'=>$rid,'client_id'=>$cid,'bureau'=>sanitize_key($r['bureau']??''),'info_type'=>sanitize_key($r['info_type']),'info_value'=>sanitize_text_field($r['info_value']),'status'=>'reported','created_at'=>current_time('mysql')));}
        if($write_failed||$this->wpdb->last_error){$this->wpdb->query('ROLLBACK');$this->wpdb->update($p.'creditos_credit_reports',array('status'=>'needs_review','parser_status'=>'failed','error_message'=>'Normalized records could not be saved. Existing records were preserved where possible.','updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));return false;}
        $counts=array();foreach(array('tradelines','collections','inquiries','personal_information')as$table)$counts[$table]=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_{$table} WHERE report_id=%d AND client_id=%d",$rid,$cid));
        $total=array_sum($counts);if($total<1){$this->wpdb->update($p.'creditos_credit_reports',array('status'=>'needs_review','parser_status'=>'needs_review','error_message'=>'No structured credit records were extracted. The source report remains available for parser refinement and reprocessing.','updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));$this->repository->audit(get_current_user_id(),$cid,'credit_report_normalization_empty','credit_report',$rid,$counts);$this->wpdb->query('ROLLBACK');return false;}
        $this->wpdb->query('COMMIT');
        $this->wpdb->update($p.'creditos_credit_reports',array('status'=>'ready_for_review','parser_status'=>'normalized','error_message'=>null,'updated_at'=>current_time('mysql')),array('id'=>$rid,'client_id'=>$cid));return true;}

    private function report_payload($rid,$cid){$p=$this->wpdb->prefix;$report=$this->wpdb->get_row($this->wpdb->prepare("SELECT id,bureau,provider,report_date,imported_at,status,parser_status,source_format,source_filename,error_message FROM {$p}creditos_credit_reports WHERE id=%d AND client_id=%d LIMIT 1",$rid,$cid),ARRAY_A);if(!$report)return null;$report['tradelines']=$this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$p}creditos_tradelines WHERE report_id=%d AND client_id=%d ORDER BY creditor_name",$rid,$cid),ARRAY_A);$report['collections']=$this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$p}creditos_collections WHERE report_id=%d AND client_id=%d ORDER BY collector_name",$rid,$cid),ARRAY_A);$report['inquiries']=$this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$p}creditos_inquiries WHERE report_id=%d AND client_id=%d ORDER BY inquiry_date DESC",$rid,$cid),ARRAY_A);$report['personal_information']=$this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$p}creditos_personal_information WHERE report_id=%d AND client_id=%d ORDER BY info_type,info_value",$rid,$cid),ARRAY_A);return$report;}
}
