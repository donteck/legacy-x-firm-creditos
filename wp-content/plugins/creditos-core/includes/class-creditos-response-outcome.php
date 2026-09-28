<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS post-delivery Response & Outcome foundation.
 * Stores only human-recorded response facts; it does not infer bureau/furnisher outcomes.
 */
class CreditOS_Response_Outcome {
    private $wpdb;
    public function __construct(){ global $wpdb; $this->wpdb=$wpdb; add_action('init',array($this,'ensure_schema'),11); add_action('rest_api_init',array($this,'routes')); }
    public function ensure_schema(){
        require_once ABSPATH.'wp-admin/includes/upgrade.php'; $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$p}creditos_response_outcomes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id BIGINT UNSIGNED NOT NULL,
            delivery_id BIGINT UNSIGNED NOT NULL,
            response_status VARCHAR(40) NOT NULL DEFAULT 'awaiting_response',
            outcome VARCHAR(60) NOT NULL DEFAULT 'pending',
            responder VARCHAR(190) NULL,
            response_reference VARCHAR(190) NULL,
            received_at DATETIME NULL,
            follow_up_due_at DATETIME NULL,
            response_notes LONGTEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY draft_id(draft_id),
            KEY delivery_id(delivery_id),
            KEY response_status(response_status),
            KEY outcome(outcome),
            KEY follow_up_due_at(follow_up_due_at)
        ) $c;");
    }
    public function routes(){
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/response-outcome',array(
            array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_record'),'permission_callback'=>array($this,'can_access')),
            array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'save_record'),'permission_callback'=>array($this,'can_manage')),
        ));
    }
    private function draft($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_dispute_drafts WHERE id=%d",absint($id)),ARRAY_A); }
    private function delivery($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_deliveries WHERE draft_id=%d LIMIT 1",absint($id)),ARRAY_A); }
    private function own_client_id(){ return (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}creditos_clients WHERE wp_user_id=%d LIMIT 1",get_current_user_id())); }
    public function can_access($r){ if(!is_user_logged_in())return false;$d=$this->draft($r['id']);if(!$d)return false;if(current_user_can('creditos_manage_disputes')||current_user_can('manage_options'))return true;$cid=$this->own_client_id();return $cid>0&&$cid===(int)$d['client_id']; }
    public function can_manage($r){ return is_user_logged_in()&&(current_user_can('creditos_manage_disputes')||current_user_can('manage_options'))&&(bool)$this->draft($r['id']); }
    public function get_record($r){
        $d=$this->draft($r['id']); if(!$d)return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        $delivery=$this->delivery($d['id']); $eligible=$delivery&&in_array($delivery['delivery_status'],array('sent','in_transit','delivered','returned'),true);
        $record=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_response_outcomes WHERE draft_id=%d LIMIT 1",$d['id']),ARRAY_A);
        return rest_ensure_response(array('draft'=>$d,'delivery'=>$delivery,'response_outcome'=>$record,'eligible'=>$eligible,'can_manage'=>current_user_can('creditos_manage_disputes')||current_user_can('manage_options')));
    }
    public function save_record($r){
        $d=$this->draft($r['id']); if(!$d)return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        $delivery=$this->delivery($d['id']); if(!$delivery||!in_array($delivery['delivery_status'],array('sent','in_transit','delivered','returned'),true))return new WP_Error('creditos_delivery_required','A real delivery event must be recorded before a response or outcome can be saved.',array('status'=>409));
        $status=sanitize_key((string)$r->get_param('response_status')); if(!in_array($status,array('awaiting_response','response_received','follow_up_required','closed'),true))return new WP_Error('creditos_response_status','Choose a valid response status.',array('status'=>400));
        $outcome=sanitize_key((string)$r->get_param('outcome')); if(!in_array($outcome,array('pending','corrected','deleted','verified','no_change','partial_change','other'),true))return new WP_Error('creditos_response_outcome','Choose a valid outcome.',array('status'=>400));
        $received=sanitize_text_field((string)$r->get_param('received_at')); if(in_array($status,array('response_received','closed'),true)&&!$received)return new WP_Error('creditos_response_date_required','Record the actual response received date.',array('status'=>400));
        if('awaiting_response'===$status&&'pending'!==$outcome)return new WP_Error('creditos_outcome_requires_response','An outcome cannot be recorded before a response is received.',array('status'=>400));
        $table=$this->wpdb->prefix.'creditos_response_outcomes'; $existing=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE draft_id=%d",$d['id']));
        $data=array('delivery_id'=>(int)$delivery['id'],'response_status'=>$status,'outcome'=>$outcome,'responder'=>sanitize_text_field((string)$r->get_param('responder')),'response_reference'=>sanitize_text_field((string)$r->get_param('response_reference')),'received_at'=>$received?:null,'follow_up_due_at'=>sanitize_text_field((string)$r->get_param('follow_up_due_at'))?:null,'response_notes'=>sanitize_textarea_field((string)$r->get_param('response_notes')),'created_by'=>get_current_user_id(),'updated_at'=>current_time('mysql'));
        if($existing){$ok=$this->wpdb->update($table,$data,array('id'=>$existing));$id=$existing;}else{$data['draft_id']=(int)$d['id'];$data['created_at']=current_time('mysql');$ok=$this->wpdb->insert($table,$data);$id=(int)$this->wpdb->insert_id;}
        if(false===$ok)return new WP_Error('creditos_response_save_failed','Response/outcome record could not be saved.',array('status'=>500));
        $record=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);
        return rest_ensure_response(array('response_outcome'=>$record,'notice'=>'Response/outcome record saved. No result was inferred or transmitted.'));
    }
}
new CreditOS_Response_Outcome();
