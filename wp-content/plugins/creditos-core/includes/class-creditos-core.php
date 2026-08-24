<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_Core {

    public function __construct() {
        add_action( 'init', array( $this, 'register_roles' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
    }

    public function register_roles() {
        add_role(
            'creditos_specialist',
            __( 'CreditOS Specialist', 'creditos' ),
            array(
                'read' => true,
                'creditos_manage_clients' => true,
                'creditos_manage_roadmaps' => true,
            )
        );

        add_role(
            'creditos_business_specialist',
            __( 'CreditOS Business Specialist', 'creditos' ),
            array(
                'read' => true,
                'creditos_manage_clients' => true,
                'creditos_manage_business_credit' => true,
                'creditos_manage_roadmaps' => true,
            )
        );

        add_role(
            'creditos_client',
            __( 'CreditOS Client', 'creditos' ),
            array(
                'read' => true,
                'creditos_view_own_profile' => true,
            )
        );
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'CreditOS', 'creditos' ),
            __( 'CreditOS', 'creditos' ),
            'manage_options',
            'creditos',
            array( $this, 'render_dashboard' ),
            'dashicons-chart-area',
            3
        );
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'CreditOS', 'creditos' ) . '</h1>';
        echo '<p>' . esc_html__( 'Legacy X Firm Credit Operating Solutions — Personal & Business Credit Intelligence, Management & Automation.', 'creditos' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Core version:', 'creditos' ) . '</strong> ' . esc_html( CREDITOS_CORE_VERSION ) . '</p>';
        echo '</div>';
    }
}
