<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_Core {
    private $repository;
    private $rest;

    public function __construct() {
        $this->maybe_upgrade();
        $this->ensure_role_capabilities();
        $this->repository = new CreditOS_Repository();
        $this->rest = new CreditOS_REST( $this->repository );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
    }

    public function maybe_upgrade() {
        if ( get_option( 'creditos_db_version' ) !== CREDITOS_CORE_VERSION ) {
            CreditOS_Activator::activate();
        }
    }

    private function ensure_role_capabilities() {
        $caps = array(
            'creditos_client' => array( 'creditos_view_own_profile', 'creditos_manage_own_onboarding', 'creditos_manage_own_documents' ),
            'creditos_specialist' => array( 'creditos_view_own_profile', 'creditos_manage_own_onboarding', 'creditos_manage_own_documents', 'creditos_manage_clients', 'creditos_manage_roadmaps', 'creditos_manage_tasks', 'creditos_manage_disputes', 'creditos_view_staff_dashboard' ),
            'creditos_business_specialist' => array( 'creditos_view_own_profile', 'creditos_manage_own_onboarding', 'creditos_manage_own_documents', 'creditos_manage_clients', 'creditos_manage_roadmaps', 'creditos_manage_tasks', 'creditos_manage_disputes', 'creditos_view_staff_dashboard', 'creditos_manage_business_credit' ),
            'administrator' => array( 'creditos_view_own_profile', 'creditos_manage_own_onboarding', 'creditos_manage_own_documents', 'creditos_manage_clients', 'creditos_manage_roadmaps', 'creditos_manage_tasks', 'creditos_manage_disputes', 'creditos_view_staff_dashboard', 'creditos_manage_business_credit' ),
        );
        foreach ( $caps as $role_name => $role_caps ) {
            $role = get_role( $role_name );
            if ( ! $role ) continue;
            foreach ( $role_caps as $cap ) {
                if ( ! $role->has_cap( $cap ) ) $role->add_cap( $cap );
            }
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            __( 'CreditOS', 'creditos' ),
            __( 'CreditOS', 'creditos' ),
            'creditos_view_staff_dashboard',
            'creditos',
            array( $this, 'render_dashboard' ),
            'dashicons-chart-area',
            3
        );
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'creditos_view_staff_dashboard' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access CreditOS staff tools.', 'creditos' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'CreditOS', 'creditos' ) . '</h1>';
        echo '<p>' . esc_html__( 'Legacy X Firm Credit Operating Solutions — Personal & Business Credit Intelligence, Management & Automation.', 'creditos' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Core version:', 'creditos' ) . '</strong> ' . esc_html( CREDITOS_CORE_VERSION ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( home_url( '/dashboard/' ) ) . '">' . esc_html__( 'Open CreditOS Dashboard', 'creditos' ) . '</a></p></div>';
    }
}
