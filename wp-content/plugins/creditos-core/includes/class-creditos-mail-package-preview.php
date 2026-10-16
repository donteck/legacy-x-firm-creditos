<?php
if (!defined('ABSPATH')) { exit; }

/** Read-only integrity preview for an approved dispute revision; no PDF or mail. */
final class CreditOS_Mail_Package_Preview {
    private $wpdb;
    public function __construct() {
        global $wpdb; $this->wpdb=$wpdb;
        add_action('rest_api_init',array($this,'routes'));
    }
    public function routes() {
        register_rest_route('creditos/v1','/drafts/(?P<id>\\d+)/mail-package-preview',array(
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>array($this,'preview'),
            'permission_callback'=>array($this,'allowed')
        ));
    }
    public function allowed($request) {
        return is_user_logged_in() && (current_user_can('creditos_manage_disputes') || current_user_can('manage_options'));
    }
    public function preview($request) {
        $p=$this->wpdb->prefix;
        $draft=$this->wpdb->get_row($this->wpdb->prepare(
            "SELECT d.*,a.approval_status AS final_approval_status,l.review_status AS legal_status,
                    cp.report_id,r.source_format
             FROM {$p}creditos_dispute_drafts d
             INNER JOIN {$p}creditos_final_approvals a ON a.draft_id=d.id
             INNER JOIN {$p}creditos_legal_reviews l ON l.draft_id=d.id
             INNER JOIN {$p}creditos_case_preparations cp ON cp.id=d.case_id AND cp.client_id=d.client_id
             INNER JOIN {$p}creditos_credit_reports r ON r.id=cp.report_id AND r.client_id=d.client_id
             WHERE d.id=%d LIMIT 1",absint($request['id'])
        ),ARRAY_A);
        if (!$draft) return new WP_Error('creditos_package_missing','Draft and review records not found.',array('status'=>404));
        if ('approved'!==$draft['approval_status'] || 'approved'!==$draft['final_approval_status'] || 'validated'!==$draft['legal_status']) {
            return new WP_Error('creditos_package_not_approved','Current draft requires validated legal review and final approval.',array('status'=>409));
        }
        $revision=(int)$draft['revision_number'];
        if ($revision<1 || ''===trim((string)$draft['recipient_name']) || ''===trim((string)$draft['subject_line']) || ''===trim((string)$draft['draft_body'])) {
            return new WP_Error('creditos_package_incomplete','Draft content or revision is incomplete.',array('status'=>409));
        }
        if ($revision>1) {
            $snapshot=$this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$p}creditos_dispute_draft_revisions WHERE draft_id=%d AND revision_number=%d LIMIT 1",
                $draft['id'],$revision
            ),ARRAY_A);
            if (!$snapshot || $snapshot['recipient_type']!==$draft['recipient_type'] ||
                $snapshot['recipient_name']!==$draft['recipient_name'] ||
                $snapshot['subject_line']!==$draft['subject_line'] ||
                $snapshot['draft_body']!==$draft['draft_body']) {
                return new WP_Error('creditos_package_revision_mismatch','Revision snapshot does not match current approved content.',array('status'=>409));
            }
        }
        $fields=array(
            'draft_id'=>(int)$draft['id'],
            'revision'=>$revision,
            'recipient_type'=>(string)$draft['recipient_type'],
            'recipient_name'=>(string)$draft['recipient_name'],
            'subject_line'=>(string)$draft['subject_line'],
            'draft_body'=>(string)$draft['draft_body']
        );
        $fingerprint=hash('sha256',wp_json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return rest_ensure_response(array(
            'draft_id'=>(int)$draft['id'],
            'revision'=>$revision,
            'content_sha256'=>$fingerprint,
            'package_reference'=>'CREDITOS-D'.(int)$draft['id'].'-R'.$revision.'-'.substr($fingerprint,0,12),
            'qa_fixture'=>('qa_fixture'===$draft['source_format']),
            'pdf_generated'=>false,
            'immutable_package_stored'=>false,
            'ready_for_mailing'=>false,
            'mail_transmitted'=>false,
            'notice'=>'Read-only integrity preview. No PDF was generated, stored, approved for mailing, or sent.'
        ));
    }
}
new CreditOS_Mail_Package_Preview();
