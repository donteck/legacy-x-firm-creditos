<?php
/**
 * Plugin Name: CreditOS Core
 * Plugin URI: https://creditos.legacyxfirm.us
 * Description: Core application services for Legacy X Firm Credit Operating Solutions (CreditOS).
 * Version: 0.4.3
 * Author: Legacy X Firm
 * Text Domain: creditos
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define('CREDITOS_CORE_VERSION','0.4.3');
define('CREDITOS_CORE_FILE',__FILE__);define('CREDITOS_CORE_DIR',plugin_dir_path(__FILE__));define('CREDITOS_CORE_URL',plugin_dir_url(__FILE__));
require_once CREDITOS_CORE_DIR.'includes/class-creditos-activator.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-repository.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-rest.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-report-parser.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-report-import.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-credit-connections.php';
require_once CREDITOS_CORE_DIR.'includes/class-creditos-core.php';
register_activation_hook(__FILE__,array('CreditOS_Activator','activate'));

/**
 * Recover report jobs left in a processing state by a terminated request.
 * A normal synchronous parse should finish well inside this window. We do not
 * delete the source report; we only return the record to a safe review state
 * so the client can retry after parser fixes.
 */
function creditos_recover_stale_report_processing(){
    global $wpdb;
    $table=$wpdb->prefix.'creditos_credit_reports';
    $cutoff=gmdate('Y-m-d H:i:s',current_time('timestamp',true)-(10*MINUTE_IN_SECONDS));
    $message='Previous report processing did not complete. The source report is preserved and can be safely reprocessed.';
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET status='needs_review', parser_status='failed', error_message=%s, updated_at=%s WHERE status='processing' AND updated_at < %s",
        $message,current_time('mysql'),$cutoff
    ));
}
add_action('init','creditos_recover_stale_report_processing',20);

function creditos_core(){static $instance=null;if(null===$instance)$instance=new CreditOS_Core();return$instance;} creditos_core();
