<?php
/**
 * Plugin Name: CreditOS Personal
 * Description: Personal CreditOS 7-step roadmap module.
 * Version: 0.1.0
 * Author: Legacy X Firm
 * Text Domain: creditos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function creditos_personal_steps() {
    return array(
        1 => 'Credit Foundation',
        2 => '3-Bureau Credit Analysis',
        3 => 'Credit Accuracy & Health Review',
        4 => 'Dispute & Correction',
        5 => 'Credit Optimization',
        6 => 'Credit Building & Strengthening',
        7 => 'Personal Funding Readiness',
    );
}
