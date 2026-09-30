<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Final Approval Gate.
 * Approval is distinct from legal validation and does not trigger delivery.
 */
class CreditOS_Final_Approval {
    private $wpdb;
    public function __construct(){ global $wpdb; $this->wpdb=$wpdb; add_action('init',array($this,'ensure_schema'),9); add_action('rest_api_init',array($this,'routes')); }
    public function ensure_schema(){
        require_once ABSPATH.'wp-admin/includes/upgrade.php'; $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$p}creditos_final_approvals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id BIGINT UNSIGNED NOT NULL,
            approval_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            approval_notes LONGTEXT NULL,
            approved_by BIGINT UNSIGNED NOT NULL,
            approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY draft_id (draft_id),
            KEY approval_status (approval_status),
            KEY approved_by (approved_by)
        ) $c;");
    }
    public function routes(){
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/final-approval',array(
            array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_approval'),'permission_callback'=>array($this,'can_access')),
            array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'save_approval'),'permission_callback'=>array($this,'can_approve')),
        ));
    }
    private function draft($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_dispute_drafts WHERE id=%d",absint($id)),ARRAY_A); }
    private function legal_review($id){ return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_legal_reviews WHERE draft_id=%d LIMIT 1",absint($id)),ARRAY_A); }
    private function safeguards_current($draft){
        $p=$this->wpdb->prefix;
        $case=$this->wpdb->get_row($this->wpdb->prepare("SELECT cp.*,COALESCE(t.review_status,c.review_status) review_status,COALESCE(t.discrepancy_type,c.discrepancy_type) discrepancy_type FROM {$p}creditos_case_preparations cp LEFT JOIN {$p}creditos_tradelines t ON t.id=cp.tradeline_id AND t.report_id=cp.report_id AND t.client_id=cp.client_id LEFT JOIN {$p}creditos_collections c ON c.id=cp.collection_id AND c.report_id=cp.report_id AND c.client_id=cp.client_id WHERE cp.id=%d AND (t.id IS NOT NULL OR c.id IS NOT NULL) LIMIT 1",$draft['case_id']),ARRAY_A);
        if(!$case||'potential_inaccuracy'!==$case['review_status']||empty($case['discrepancy_type'])||'none'===$case['discrepancy_type'])return false;
        if(!empty($case['collection_id']))$accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_collection_evidence WHERE report_id=%d AND collection_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['collection_id'],$case['client_id']));
        else $accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_tradeline_evidence WHERE report_id=%d AND tradeline_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['tradeline_id'],$case['client_id']));
        return $accepted>0;
    }
    private function own_client_id(){ return (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}creditos_clients WHERE wp_user_id=%d LIMIT 1",get_current_user_id())); }
    public function can_access($r){
        if(!is_user_logged_in()) return false; $d=$this->draft($r['id']); if(!$d) return false;
        if(current_user_can('creditos_manage_disputes')||current_user_can('manage_options')) return true;
        return $this->own_client_id()>0 && $this->own_client_id()===(int)$d['client_id'];
    }
    public function can_approve($r){ return is_user_logged_in() && (current_user_can('creditos_manage_disputes')||current_user_can('manage_options')) && (bool)$this->draft($r['id']); }
    public function get_approval($r){
        $d=$this->draft($r['id']); if(!$d) return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        if(!$this->safeguards_current($d))return new WP_Error('creditos_case_revalidation_required','Underlying case safeguards changed. Restore them before Final Approval.',array('status'=>409));
        $legal=$this->legal_review($d['id']);
        $approval=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_final_approvals WHERE draft_id=%d LIMIT 1",$d['id']),ARRAY_A);
        return rest_ensure_response(array('draft'=>$d,'legal_review'=>$legal,'approval'=>$approval,'eligible'=>($legal && 'validated'===$legal['review_status']),'can_approve'=>current_user_can('creditos_manage_disputes')||current_user_can('manage_options')));
    }
    public function save_approval($r){
        $d=$this->draft($r['id']); if(!$d) return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        $legal=$this->legal_review($d['id']);
        if(!$legal || 'validated'!==$legal['review_status']) return new WP_Error('creditos_legal_review_required','Final approval is locked until Legal & Compliance Review is validated.',array('status'=>409));
        $status=sanitize_key((string)$r->get_param('approval_status'));
        if(!in_array($status,array('pending','changes_required','approved'),true)) return new WP_Error('creditos_approval_status','Choose a valid approval status.',array('status'=>400));
        $notes=sanitize_textarea_field((string)$r->get_param('approval_notes'));
        $table=$this->wpdb->prefix.'creditos_final_approvals';
        $existing=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE draft_id=%d",$d['id']));
        $data=array('approval_status'=>$status,'approval_notes'=>$notes,'approved_by'=>get_current_user_id(),'approved_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));
        if($existing){ $ok=$this->wpdb->update($table,$data,array('id'=>$existing)); $id=$existing; }
        else { $data['draft_id']=(int)$d['id']; $ok=$this->wpdb->insert($table,$data); $id=(int)$this->wpdb->insert_id; }
        if(false===$ok) return new WP_Error('creditos_approval_save_failed','Final approval could not be saved.',array('status'=>500));
        $this->wpdb->update($this->wpdb->prefix.'creditos_dispute_drafts',array('approval_status'=>$status,'updated_at'=>current_time('mysql')),array('id'=>(int)$d['id']));
        $approval=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);
        return rest_ensure_response(array('approval'=>$approval,'notice'=>('approved'===$status?'Draft approved for the future delivery stage. Nothing was sent or mailed.':'Final approval status saved. Nothing was sent or mailed.')));
    }
}
new CreditOS_Final_Approval();
