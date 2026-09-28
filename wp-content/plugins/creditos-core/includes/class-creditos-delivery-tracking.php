<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Delivery & Tracking foundation.
 * Records delivery preparation and externally supplied tracking data.
 * No carrier/provider transmission is performed by this class.
 */
class CreditOS_Delivery_Tracking {
    private $wpdb;
    public function __construct(){ global $wpdb; $this->wpdb=$wpdb; add_action('init',array($this,'ensure_schema'),10); add_action('rest_api_init',array($this,'routes')); }
    public function ensure_schema(){
        require_once ABSPATH.'wp-admin/includes/upgrade.php'; $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$p}creditos_deliveries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id BIGINT UNSIGNED NOT NULL,
            approval_id BIGINT UNSIGNED NOT NULL,
            delivery_status VARCHAR(40) NOT NULL DEFAULT 'ready',
            delivery_method VARCHAR(40) NOT NULL DEFAULT 'manual_mail',
            provider VARCHAR(80) NULL,
            provider_reference VARCHAR(190) NULL,
            tracking_number VARCHAR(190) NULL,
            sent_at DATETIME NULL,
            delivered_at DATETIME NULL,
            response_due_at DATETIME NULL,
            delivery_notes LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY draft_id(draft_id),
            KEY approval_id(approval_id),
            KEY delivery_status(delivery_status),
            KEY tracking_number(tracking_number)
        ) $c;");
    }
    public function routes(){
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/delivery',array(
            array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_delivery'),'permission_callback'=>array($this,'can_access')),
            array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'save_delivery'),'permission_callback'=>array($this,'can_manage')),
        ));
    }
    private function draft($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_dispute_drafts WHERE id=%d",absint($id)),ARRAY_A); }
    private function approval($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_final_approvals WHERE draft_id=%d LIMIT 1",absint($id)),ARRAY_A); }
    private function safeguards_current($draft){
        $p=$this->wpdb->prefix;
        $case=$this->wpdb->get_row($this->wpdb->prepare("SELECT cp.*,COALESCE(t.review_status,c.review_status) review_status,COALESCE(t.discrepancy_type,c.discrepancy_type) discrepancy_type FROM {$p}creditos_case_preparations cp LEFT JOIN {$p}creditos_tradelines t ON t.id=cp.tradeline_id AND t.report_id=cp.report_id AND t.client_id=cp.client_id LEFT JOIN {$p}creditos_collections c ON c.id=cp.collection_id AND c.report_id=cp.report_id AND c.client_id=cp.client_id WHERE cp.id=%d AND (t.id IS NOT NULL OR c.id IS NOT NULL) LIMIT 1",$draft['case_id']),ARRAY_A);
        if(!$case||'potential_inaccuracy'!==$case['review_status']||empty($case['discrepancy_type'])||'none'===$case['discrepancy_type'])return false;
        if(!empty($case['collection_id']))$accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_collection_evidence WHERE report_id=%d AND collection_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['collection_id'],$case['client_id']));
        else $accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_tradeline_evidence WHERE report_id=%d AND tradeline_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['tradeline_id'],$case['client_id']));
        return $accepted>0;
    }
    private function own_client_id(){ return (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}creditos_clients WHERE wp_user_id=%d LIMIT 1",get_current_user_id())); }
    public function can_access($r){ if(!is_user_logged_in())return false;$d=$this->draft($r['id']);if(!$d)return false;if(current_user_can('creditos_manage_disputes')||current_user_can('manage_options'))return true;$cid=$this->own_client_id();return $cid>0&&$cid===(int)$d['client_id']; }
    public function can_manage($r){ return is_user_logged_in()&&(current_user_can('creditos_manage_disputes')||current_user_can('manage_options'))&&(bool)$this->draft($r['id']); }
    public function get_delivery($r){
        $d=$this->draft($r['id']); if(!$d)return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        if(!$this->safeguards_current($d))return new WP_Error('creditos_case_revalidation_required','Underlying case safeguards changed. Restore them before Delivery & Tracking.',array('status'=>409));
        $a=$this->approval($d['id']);
        $delivery=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_deliveries WHERE draft_id=%d LIMIT 1",$d['id']),ARRAY_A);
        return rest_ensure_response(array('draft'=>$d,'approval'=>$a,'delivery'=>$delivery,'eligible'=>($a&&'approved'===$a['approval_status']),'provider_connected'=>false,'can_manage'=>current_user_can('creditos_manage_disputes')||current_user_can('manage_options')));
    }
    public function save_delivery($r){
        $d=$this->draft($r['id']); if(!$d)return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        $a=$this->approval($d['id']); if(!$a||'approved'!==$a['approval_status'])return new WP_Error('creditos_approval_required','Delivery is locked until Final Approval is Approved.',array('status'=>409));
        $status=sanitize_key((string)$r->get_param('delivery_status'));
        if(!in_array($status,array('ready','prepared','sent','in_transit','delivered','returned','failed'),true))return new WP_Error('creditos_delivery_status','Choose a valid delivery status.',array('status'=>400));
        $method=sanitize_key((string)$r->get_param('delivery_method')); if(!in_array($method,array('manual_mail','certified_mail','provider_api'),true))$method='manual_mail';
        if('provider_api'===$method)return new WP_Error('creditos_provider_not_connected','No live mail-provider API is connected yet. Configure and verify a provider before API delivery.',array('status'=>409));
        $provider=sanitize_text_field((string)$r->get_param('provider'));
        $reference=sanitize_text_field((string)$r->get_param('provider_reference'));
        $tracking=sanitize_text_field((string)$r->get_param('tracking_number'));
        $notes=sanitize_textarea_field((string)$r->get_param('delivery_notes'));
        $sent=sanitize_text_field((string)$r->get_param('sent_at')); $delivered=sanitize_text_field((string)$r->get_param('delivered_at')); $due=sanitize_text_field((string)$r->get_param('response_due_at'));
        $table=$this->wpdb->prefix.'creditos_deliveries'; $existing=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE draft_id=%d",$d['id']));
        $data=array('approval_id'=>(int)$a['id'],'delivery_status'=>$status,'delivery_method'=>$method,'provider'=>$provider,'provider_reference'=>$reference,'tracking_number'=>$tracking,'sent_at'=>$sent?:null,'delivered_at'=>$delivered?:null,'response_due_at'=>$due?:null,'delivery_notes'=>$notes,'created_by'=>get_current_user_id(),'updated_at'=>current_time('mysql'));
        if($existing){$ok=$this->wpdb->update($table,$data,array('id'=>$existing));$id=$existing;}else{$data['draft_id']=(int)$d['id'];$data['created_at']=current_time('mysql');$ok=$this->wpdb->insert($table,$data);$id=(int)$this->wpdb->insert_id;}
        if(false===$ok)return new WP_Error('creditos_delivery_save_failed','Delivery record could not be saved.',array('status'=>500));
        $delivery=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);
        return rest_ensure_response(array('delivery'=>$delivery,'notice'=>'Delivery/tracking record saved. No mail-provider transmission was performed.'));
    }
}
new CreditOS_Delivery_Tracking();
