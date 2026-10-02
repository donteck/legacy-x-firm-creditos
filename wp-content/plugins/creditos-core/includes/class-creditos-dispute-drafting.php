<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Dispute Drafting Engine
 *
 * Creates a human-reviewable draft only from a prepared internal case.
 * It does not approve, send, mail, or submit a dispute.
 */
class CreditOS_Dispute_Drafting {
    private $wpdb;
    public function __construct() {
        global $wpdb; $this->wpdb=$wpdb;
        add_action('init',array($this,'ensure_schema'),7);
        add_action('rest_api_init',array($this,'register_routes'));
    }
    public function ensure_schema() {
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $p=$this->wpdb->prefix; $c=$this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$p}creditos_dispute_drafts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            case_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            dispute_item_id BIGINT UNSIGNED NOT NULL,
            draft_status VARCHAR(30) NOT NULL DEFAULT 'draft',
            recipient_type VARCHAR(30) NOT NULL DEFAULT 'bureau',
            recipient_name VARCHAR(190) NULL,
            subject_line VARCHAR(255) NULL,
            draft_body LONGTEXT NULL,
            legal_review_status VARCHAR(30) NOT NULL DEFAULT 'not_reviewed',
            approval_status VARCHAR(30) NOT NULL DEFAULT 'not_approved',
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY case_id (case_id),
            KEY client_id (client_id),
            KEY dispute_item_id (dispute_item_id),
            KEY draft_status (draft_status)
        ) $c;");
    }
    public function register_routes() {
        register_rest_route('creditos/v1','/cases/(?P<id>\\d+)/draft',array(
            array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'get_draft'),'permission_callback'=>array($this,'can_access_case')),
            array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'create_draft'),'permission_callback'=>array($this,'can_manage_draft')),
            array('methods'=>WP_REST_Server::EDITABLE,'callback'=>array($this,'update_draft'),'permission_callback'=>array($this,'can_manage_draft')),
        ));
    }
    private function case_record($id) {
        $p=$this->wpdb->prefix;
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT cp.*,di.bureau,di.furnisher,di.account_label,di.dispute_reason,
                    COALESCE(t.creditor_name,c.collector_name) AS creditor_name,
                    t.account_number_masked,
                    COALESCE(t.discrepancy_type,c.discrepancy_type) AS discrepancy_type,
                    COALESCE(t.discrepancy_field,c.discrepancy_field) AS discrepancy_field,
                    COALESCE(t.discrepancy_details,c.discrepancy_details) AS discrepancy_details
             FROM {$p}creditos_case_preparations cp
             INNER JOIN {$p}creditos_dispute_items di ON di.id=cp.dispute_item_id
             LEFT JOIN {$p}creditos_tradelines t ON t.id=cp.tradeline_id AND t.report_id=cp.report_id AND t.client_id=cp.client_id
             LEFT JOIN {$p}creditos_collections c ON c.id=cp.collection_id AND c.report_id=cp.report_id AND c.client_id=cp.client_id
             WHERE cp.id=%d AND cp.case_status='preparing' AND di.candidate_status='candidate' AND (t.id IS NOT NULL OR c.id IS NOT NULL)",
            absint($id)
        ),ARRAY_A);
    }
    private function case_is_current($case) {
        $p=$this->wpdb->prefix;
        if(
            'potential_inaccuracy'!==($case['review_status']??null)
            || empty($case['discrepancy_type'])
            || 'none'===($case['discrepancy_type']??null)
            || empty($case['discrepancy_field'])
            || ''===trim((string)($case['discrepancy_details']??''))
        ) return false;
        if(!empty($case['collection_id'])) $accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_collection_evidence WHERE report_id=%d AND collection_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['collection_id'],$case['client_id']));
        else $accepted=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$p}creditos_tradeline_evidence WHERE report_id=%d AND tradeline_id=%d AND client_id=%d AND review_status='accepted'",$case['report_id'],$case['tradeline_id'],$case['client_id']));
        return $accepted>0;
    }
    private function own_client_id() {
        return (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}creditos_clients WHERE wp_user_id=%d LIMIT 1",get_current_user_id()
        ));
    }
    public function can_access_case($request) {
        if(!is_user_logged_in()) return false;
        $case=$this->case_record($request['id']); if(!$case) return false;
        if(current_user_can('creditos_manage_disputes')||current_user_can('manage_options')) return true;
        return $this->own_client_id()>0 && $this->own_client_id()===(int)$case['client_id'];
    }
    public function can_manage_draft($request) {
        return is_user_logged_in() && (current_user_can('creditos_manage_disputes')||current_user_can('manage_options')) && (bool)$this->case_record($request['id']);
    }
    public function get_draft($request) {
        $case=$this->case_record($request['id']);
        if(!$case) return new WP_Error('creditos_case_missing','Prepared case not found.',array('status'=>404));
        if(!$this->case_is_current($case)) return new WP_Error('creditos_case_revalidation_required','Case safeguards changed after preparation. Restore Potential Inaccuracy review, a documented discrepancy, and accepted evidence before continuing the downstream workflow.',array('status'=>409));
        $draft=$this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}creditos_dispute_drafts WHERE case_id=%d LIMIT 1",$case['id']
        ),ARRAY_A);
        return rest_ensure_response(array('case'=>$case,'draft'=>$draft,'can_create'=>current_user_can('creditos_manage_disputes')||current_user_can('manage_options')));
    }
    public function update_draft($request) {
        $case=$this->case_record($request['id']);
        if(!$case) return new WP_Error('creditos_case_missing','Prepared case not found.',array('status'=>404));
        if(!$this->case_is_current($case)) return new WP_Error('creditos_case_revalidation_required','Case safeguards changed after preparation. Restore the required factual review and accepted evidence before editing this draft.',array('status'=>409));
        $table=$this->wpdb->prefix.'creditos_dispute_drafts';
        $draft=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE case_id=%d LIMIT 1",$case['id']),ARRAY_A);
        if(!$draft) return new WP_Error('creditos_draft_missing','Draft not found.',array('status'=>404));
        if('not_reviewed'!==($draft['legal_review_status']??'not_reviewed') || 'not_approved'!==($draft['approval_status']??'not_approved')) {
            return new WP_Error('creditos_draft_locked','This draft is locked because Legal Review or Final Approval has already started. Create a controlled revision workflow before changing reviewed content.',array('status'=>409));
        }
        $recipient_type=sanitize_key((string)$request->get_param('recipient_type'));
        if(!in_array($recipient_type,array('bureau','furnisher'),true)) $recipient_type=$draft['recipient_type'];
        $recipient=sanitize_text_field((string)$request->get_param('recipient_name'));
        $subject=sanitize_text_field((string)$request->get_param('subject_line'));
        $body=sanitize_textarea_field((string)$request->get_param('draft_body'));
        if(''===$recipient || ''===$subject || ''===$body) return new WP_Error('creditos_draft_fields_required','Recipient, subject, and draft body are required.',array('status'=>400));
        $ok=$this->wpdb->update($table,array(
            'recipient_type'=>$recipient_type,'recipient_name'=>$recipient,'subject_line'=>$subject,'draft_body'=>$body,
            'draft_status'=>'draft','updated_at'=>current_time('mysql')
        ),array('id'=>(int)$draft['id']));
        if(false===$ok) return new WP_Error('creditos_draft_update_failed','Draft changes could not be saved.',array('status'=>500));
        $saved=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$draft['id']),ARRAY_A);
        return rest_ensure_response(array('updated'=>true,'draft'=>$saved,'notice'=>'Draft changes saved. Legal Review and Final Approval remain locked until the draft is reviewed.'));
    }

    private function factual_template($case) {
        $creditor=$case['creditor_name']?:$case['furnisher']?:'the reporting entity';
        $account=$case['account_number_masked']?' (account '.$case['account_number_masked'].')':'';
        $issue=trim((string)$case['issue_summary']);
        $resolution=ucwords(str_replace('_',' ',(string)$case['requested_resolution']));
        return "To Whom It May Concern,\n\nI am writing regarding information reported by {$creditor}{$account}. I am requesting an investigation of the following reported information:\n\n{$issue}\n\nRequested resolution: {$resolution}.\n\nPlease investigate the identified information and provide the results of your investigation in accordance with applicable requirements. Supporting documentation associated with this case should be reviewed with the reported account information.\n\nSincerely,\n[Consumer Name]";
    }
    public function create_draft($request) {
        $case=$this->case_record($request['id']);
        if(!$case) return new WP_Error('creditos_case_missing','Prepared case not found.',array('status'=>404));
        if(!$this->case_is_current($case)) return new WP_Error('creditos_case_revalidation_required','Case safeguards changed after preparation. Restore Potential Inaccuracy review, a documented discrepancy, and accepted evidence before creating or continuing a draft.',array('status'=>409));
        $table=$this->wpdb->prefix.'creditos_dispute_drafts';
        $existing=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE case_id=%d LIMIT 1",$case['id']),ARRAY_A);
        if($existing) return rest_ensure_response(array('created'=>false,'draft'=>$existing,'notice'=>'Existing draft returned.'));

        $recipient_type=sanitize_key((string)$request->get_param('recipient_type'));
        if(!in_array($recipient_type,array('bureau','furnisher'),true)) $recipient_type='bureau';
        $recipient=sanitize_text_field((string)$request->get_param('recipient_name'));
        if(''===$recipient) $recipient='bureau'===$recipient_type?strtoupper((string)$case['bureau']):(string)($case['furnisher']?:$case['creditor_name']);
        $subject=sanitize_text_field((string)$request->get_param('subject_line'));
        if(''===$subject) $subject='Request for investigation of reported credit information';
        $body=sanitize_textarea_field((string)$request->get_param('draft_body'));
        if(''===$body) $body=$this->factual_template($case);

        $ok=$this->wpdb->insert($table,array(
            'case_id'=>(int)$case['id'],'client_id'=>(int)$case['client_id'],'dispute_item_id'=>(int)$case['dispute_item_id'],
            'draft_status'=>'draft','recipient_type'=>$recipient_type,'recipient_name'=>$recipient,'subject_line'=>$subject,'draft_body'=>$body,
            'legal_review_status'=>'not_reviewed','approval_status'=>'not_approved','created_by'=>get_current_user_id(),
            'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')
        ));
        if(!$ok) return new WP_Error('creditos_draft_failed','Draft could not be created.',array('status'=>500));
        $draft=$this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$this->wpdb->insert_id),ARRAY_A);
        return rest_ensure_response(array(
            'created'=>true,'draft'=>$draft,
            'notice'=>'Draft created for human review. It is not legal-approved, approved for delivery, sent, mailed, or submitted.'
        ));
    }
}
new CreditOS_Dispute_Drafting();
