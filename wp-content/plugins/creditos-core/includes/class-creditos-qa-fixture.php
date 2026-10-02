<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin-only synthetic workflow fixture for visually testing CreditOS.
 * Never mutates or reuses a real consumer report/account.
 */
class CreditOS_QA_Fixture {
    private $wpdb;
    public function __construct(){
        global $wpdb; $this->wpdb=$wpdb;
        add_action('admin_menu',array($this,'menu'));
        add_action('admin_post_creditos_create_qa_fixture',array($this,'create'));
    }
    public function menu(){
        add_management_page('CreditOS QA Fixture','CreditOS QA Fixture','manage_options','creditos-qa-fixture',array($this,'page'));
    }
    public function page(){
        if(!current_user_can('manage_options')) wp_die('Unauthorized.');
        $url=admin_url('admin-post.php');
        echo '<div class="wrap"><h1>CreditOS QA Fixture</h1><p><strong>TEST DATA ONLY.</strong> This creates an isolated synthetic report, tradeline, and accepted evidence record so the Review → Evidence → Case → Draft → Legal → Approval workflow can be inspected without changing a real consumer record.</p>';
        echo '<form method="post" action="'.esc_url($url).'">';
        wp_nonce_field('creditos_create_qa_fixture');
        echo '<input type="hidden" name="action" value="creditos_create_qa_fixture">';
        submit_button('Create / Open QA Workflow');
        echo '</form></div>';
    }
    private function test_client(){
        $t=$this->wpdb->prefix.'creditos_clients';
        $uid=get_current_user_id();
        /*
         * Account Inspector REST endpoints are intentionally scoped to the
         * logged-in user's CreditOS client. Therefore the QA report must live
         * under that same client; otherwise the UI correctly returns
         * "Credit report not found." We never modify a real tradeline.
         */
        $id=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$t} WHERE wp_user_id=%d LIMIT 1",$uid));
        if($id) return $id;
        $this->wpdb->insert($t,array('wp_user_id'=>$uid,'client_type'=>'personal','status'=>'active','first_name'=>'QA','last_name'=>'TEST DATA','email'=>'qa-fixture-'.absint($uid).'@creditos.invalid','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
        return (int)$this->wpdb->insert_id;
    }
    public function create(){
        if(!current_user_can('manage_options')) wp_die('Unauthorized.');
        check_admin_referer('creditos_create_qa_fixture');
        $p=$this->wpdb->prefix; $client=$this->test_client();
        $reports=$p.'creditos_credit_reports';
        $report=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$reports} WHERE client_id=%d AND source_filename=%s ORDER BY id DESC LIMIT 1",$client,'CREDITOS-QA-TEST-DATA.pdf'));
        if(!$report){
            $this->wpdb->insert($reports,array('client_id'=>$client,'bureau'=>'experian','provider'=>'qa_fixture','report_date'=>current_time('Y-m-d'),'imported_at'=>current_time('mysql'),'status'=>'ready_for_review','parser_status'=>'complete','source_format'=>'qa_fixture','source_filename'=>'CREDITOS-QA-TEST-DATA.pdf','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
            $report=(int)$this->wpdb->insert_id;
        }
        $tradelines=$p.'creditos_tradelines';
        $tradeline=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$tradelines} WHERE report_id=%d AND creditor_name=%s LIMIT 1",$report,'CREDITOS QA TEST ACCOUNT'));
        if(!$tradeline){
            $this->wpdb->insert($tradelines,array(
                'report_id'=>$report,'client_id'=>$client,'bureau'=>'EXPERIAN','creditor_name'=>'CREDITOS QA TEST ACCOUNT',
                'account_number_masked'=>'TESTXXXX','account_type'=>'QA Fixture','status'=>'TEST DATA — NOT A REAL CONSUMER ACCOUNT',
                'balance'=>100.00,'past_due'=>0.00,'payment_status'=>'QA TEST','review_status'=>'potential_inaccuracy',
                'reviewer_notes'=>'QA fixture only: synthetic discrepancy for workflow validation.',
                'discrepancy_type'=>'balance','discrepancy_field'=>'balance',
                'discrepancy_details'=>'QA TEST ONLY: synthetic reported balance is intentionally different from the synthetic expected balance.',
                'evidence_status'=>'reviewed','evidence_notes'=>'Synthetic QA evidence accepted for workflow testing only.',
                'reviewed_by'=>get_current_user_id(),'reviewed_at'=>current_time('mysql'),'created_at'=>current_time('mysql')
            ));
            $tradeline=(int)$this->wpdb->insert_id;
        }
        $evidence=$p.'creditos_tradeline_evidence';
        $has=(int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$evidence} WHERE report_id=%d AND tradeline_id=%d AND checksum=%s LIMIT 1",$report,$tradeline,'creditos-qa-fixture'));
        if(!$has) $this->wpdb->insert($evidence,array(
            'report_id'=>$report,'client_id'=>$client,'tradeline_id'=>$tradeline,'attachment_id'=>0,
            'file_name'=>'QA-TEST-EVIDENCE.txt','mime_type'=>'text/plain','file_size'=>0,'checksum'=>'creditos-qa-fixture',
            'evidence_type'=>'other','review_status'=>'accepted','reviewed_by'=>get_current_user_id(),'reviewed_at'=>current_time('mysql'),
            'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')
        ));
        $target=home_url('/account-inspector/?report='.$report.'&tradeline='.$tradeline.'&qa_fixture=1');
        wp_safe_redirect($target); exit;
    }
}
new CreditOS_QA_Fixture();
