<?php
/**
 * Plugin Name: CreditOS Core
 * Plugin URI: https://creditos.legacyxfirm.us
 * Description: Core application services for Legacy X Firm Credit Operating Solutions (CreditOS).
 * Version: 0.3.1
 * Author: Legacy X Firm
 * Text Domain: creditos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CREDITOS_CORE_VERSION', '0.3.1' );
define( 'CREDITOS_CORE_FILE', __FILE__ );
define( 'CREDITOS_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CREDITOS_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CREDITOS_CORE_DIR . 'includes/class-creditos-activator.php';
require_once CREDITOS_CORE_DIR . 'includes/class-creditos-repository.php';
require_once CREDITOS_CORE_DIR . 'includes/class-creditos-rest.php';
require_once CREDITOS_CORE_DIR . 'includes/class-creditos-report-import.php';
require_once CREDITOS_CORE_DIR . 'includes/class-creditos-credit-connections.php';
require_once CREDITOS_CORE_DIR . 'includes/class-creditos-core.php';

register_activation_hook( __FILE__, array( 'CreditOS_Activator', 'activate' ) );

function creditos_core() {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new CreditOS_Core();
    }
    return $instance;
}

creditos_core();
