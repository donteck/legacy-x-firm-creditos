<?php
/**
 * Plugin Name: CreditOS Business
 * Description: Business CreditOS 7-step roadmap module.
 * Version: 0.1.0
 * Author: Legacy X Firm
 * Text Domain: creditos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function creditos_business_steps() {
    return array(
        1 => 'Business Foundation',
        2 => 'Business Fundability',
        3 => 'Business Credit Bureau Setup',
        4 => 'Vendor Credit / Net Terms',
        5 => 'Revolving Business Credit',
        6 => 'Business Credit Strengthening',
        7 => 'Business Funding Readiness',
    );
}
