<?php
if (!defined('ABSPATH')) { exit; }

/** Admin-only, read-only simulation for a verified synthetic QA report. */
final class CreditOS_Mail_Sandbox_Test {
    private $wpdb;
    public function __construct() {
        global $wpdb;
        $this->wpdb=$wpdb;
        add_action('rest_api_init',array($this,'routes'));
    }
    public function routes() {
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/mail-sandbox-test',array(
            'methods'=>WP_REST_Server::CREATABLE,
            'callback'=>array($this,'run'),
            'permission_callback'=>array($this,'allowed')
        ));
    }
    public function allowed($request) {
        return is_user_logged_in() && current_user_can('manage_options');
    }
    public function run($request) {
        if (true!==rest_sanitize_boolean($request->get_param('sandbox_acknowledged'))) {
            return new WP_Error('creditos_sandbox_ack_required','Acknowledge simulation-only testing.',array('status'=>400));
        }
        $p=$this->wpdb->prefix;
        $row=$this->wpdb->get_row($this->wpdb->prepare(
            "SELECT d.*,cp.report_id,r.source_filename,r.source_format,r.provider AS report_provider,
                    a.approval_status AS final_approval_status
             FROM {$p}creditos_dispute_drafts d
             INNER JOIN {$p}creditos_case_preparations cp ON cp.id=d.case_id AND cp.client_id=d.client_id
             INNER JOIN {$p}creditos_credit_reports r ON r.id=cp.report_id AND r.client_id=d.client_id
             INNER JOIN {$p}creditos_final_approvals a ON a.draft_id=d.id
             WHERE d.id=%d LIMIT 1",absint($request['id'])
        ),ARRAY_A);
        if (!$row) return new WP_Error('creditos_sandbox_draft_missing','Approved draft not found.',array('status'=>404));
        if ('qa_fixture'!==$row['source_format'] || 'qa_fixture'!==$row['report_provider'] ||
            'CREDITOS-QA-TEST-DATA.pdf'!==$row['source_filename']) {
            return new WP_Error('creditos_sandbox_fixture_only','Only verified synthetic QA reports may be simulated.',array('status'=>403));
        }
        if ('approved'!==$row['final_approval_status'] || 'approved'!==$row['approval_status'] ||
            'validated'!==$row['legal_review_status']) {
            return new WP_Error('creditos_sandbox_approval_required','Legal review and final approval must be current.',array('status'=>409));
        }
        $revision=max(1,(int)$row['revision_number']);
        if ($revision>1) {
            $snapshot=(int)$this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}creditos_dispute_draft_revisions
                 WHERE draft_id=%d AND revision_number=%d AND recipient_name=%s AND subject_line=%s AND draft_body=%s",
                $row['id'],$revision,$row['recipient_name'],$row['subject_line'],$row['draft_body']
            ));
            if (!$snapshot) return new WP_Error('creditos_sandbox_revision_mismatch','Current draft revision snapshot is missing or changed.',array('status'=>409));
        }
        $adapter=new CreditOS_Mail_Sandbox_Provider();
        $result=$adapter->submit(array(
            'draft_id'=>(int)$row['id'],'revision'=>$revision,
            'recipient'=>$row['recipient_name'],'qa_fixture'=>true
        ),array('sandbox_acknowledged'=>true));
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(array(
            'draft_id'=>(int)$row['id'],'revision'=>$revision,
            'simulation'=>$result,
            'delivery_record_updated'=>false,
            'notice'=>'QA simulation only. No letter transmitted and no delivery status changed.'
        ));
    }
}
new CreditOS_Mail_Sandbox_Test();
