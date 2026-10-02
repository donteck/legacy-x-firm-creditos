<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Legal & Compliance Review.
 * Human validation gate between drafting and final approval.
 */
class CreditOS_Legal_Review {
    private $wpdb;
    public function __construct(){ global $wpdb; $this->wpdb=$wpdb; add_action('init',array($this,'ensure_schema'),8); add_action('rest_api_init',array($this,'routes')); }
    public function ensure_schema(){
        require_once ABSPATH.'wp-admin/includes/upgrade.php'; $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$p}creditos_legal_reviews (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id BIGINT UNSIGNED NOT NULL,
            draft_revision INT UNSIGNED NOT NULL DEFAULT 1,
            review_status VARCHAR(30) NOT NULL DEFAULT 'in_review',
            legal_references LONGTEXT NULL,
            reviewer_notes LONGTEXT NULL,
            reviewer_id BIGINT UNSIGNED NOT NULL,
            reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY draft_id (draft_id),
            KEY review_status (review_status),
            KEY reviewer_id (reviewer_id)
        ) $c;");
    }
    public function routes(){
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/legal-review',array(
            array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_review'),'permission_callback'=>array($this,'can_access')),
            array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'save_review'),'permission_callback'=>array($this,'can_review')),
        ));
    }
    private function draft($id){
        $p=$this->wpdb->prefix;
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT d.*,cp.issue_summary,cp.requested_resolution,cp.report_id,cp.tradeline_id
             FROM {$p}creditos_dispute_drafts d
             INNER JOIN {$p}creditos_case_preparations cp ON cp.id=d.case_id
             WHERE d.id=%d",absint($id)
        ),ARRAY_A);
    }
    private function safeguards_current($draft){
        $p=$this->wpdb->prefix;
        $case=$this->wpdb->get_row($this->wpdb->prepare("SELECT cp.*,COALESCE(t.review_status,c.review_status) review_status,COALESCE(t.discrepancy_type,c.discrepancy_type) discrepancy_type,COALESCE(t.discrepancy_field,c.discrepancy_field) discrepancy_field,COALESCE(t.discrepancy_details,c.discrepancy_details) discrepancy_details FROM {$p}creditos_case_preparations cp LEFT JOIN {$p}creditos_tradelines t ON t.id=cp.tradeline_id AND t.report_id=cp.report_id AND t.client_id=cp.client_id LEFT JOIN {$p}creditos_collections c ON c.id=cp.collection_id AND c.report_id=cp.report_id AND c.client_id=cp.client_id WHERE cp.id=%d AND (t.id IS NOT NULL OR c.id IS NOT NULL) LIMIT 1",$draft['case_id']),ARRAY_A);
        if(!$case||'potential_inaccuracy'!==$case['review_status']||empty($case['discrepancy_type'])||'none'===$case['discrepancy_type']||empty($case['discrepancy_field'])||''===trim((string)$case['discrepancy_details']))return false;
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
    public function can_review($r){ return is_user_logged_in() && (current_user_can('creditos_manage_disputes')||current_user_can('manage_options')) && (bool)$this->draft($r['id']); }
    public function get_review($r){
        $d=$this->draft($r['id']); if(!$d) return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        if(!$this->safeguards_current($d))return new WP_Error('creditos_case_revalidation_required','Underlying case safeguards changed. Restore the required review, discrepancy, and accepted evidence before Legal Review.',array('status'=>409));
        $review=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}creditos_legal_reviews WHERE draft_id=%d LIMIT 1",$d['id']),ARRAY_A);
        if($review && (int)($review['draft_revision']??1)!==(int)($d['revision_number']??1)) $review=null;
        return rest_ensure_response(array('draft'=>$d,'review'=>$review,'can_review'=>current_user_can('creditos_manage_disputes')||current_user_can('manage_options')));
    }
    public function save_review($r){
        $d=$this->draft($r['id']); if(!$d) return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        if(!$this->safeguards_current($d))return new WP_Error('creditos_case_revalidation_required','Underlying case safeguards changed. Restore the required review, discrepancy, and accepted evidence before saving Legal Review.',array('status'=>409));
        $status=sanitize_key((string)$r->get_param('review_status'));
        if(!in_array($status,array('in_review','changes_required','validated'),true)) return new WP_Error('creditos_review_status','Choose a valid legal review status.',array('status'=>400));
        $refs=sanitize_textarea_field((string)$r->get_param('legal_references'));
        $notes=sanitize_textarea_field((string)$r->get_param('reviewer_notes'));
        if('validated'===$status && ''===trim($refs)) return new WP_Error('creditos_references_required','Validated review requires documented legal/reference sources.',array('status'=>409));
        $table=$this->wpdb->prefix.'creditos_legal_reviews';
        $existing=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE draft_id=%d",$d['id']));
        $data=array('draft_revision'=>max(1,(int)($d['revision_number']??1)),'review_status'=>$status,'legal_references'=>$refs,'reviewer_notes'=>$notes,'reviewer_id'=>get_current_user_id(),'reviewed_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'));
        if($existing){ $ok=$this->wpdb->update($table,$data,array('id'=>$existing)); $id=$existing; }
        else { $data['draft_id']=(int)$d['id']; $ok=$this->wpdb->insert($table,$data); $id=(int)$this->wpdb->insert_id; }
        if(false===$ok) return new WP_Error('creditos_review_save_failed','Legal review could not be saved.',array('status'=>500));
        $this->wpdb->update($this->wpdb->prefix.'creditos_dispute_drafts',array('legal_review_status'=>$status,'updated_at'=>current_time('mysql')),array('id'=>(int)$d['id']));
        $review=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);
        return rest_ensure_response(array('review'=>$review,'notice'=>'Legal/compliance review saved. Final delivery approval remains separate.'));
    }
}
new CreditOS_Legal_Review();
