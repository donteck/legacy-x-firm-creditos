<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_Activator {

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        $tables = array();

        $tables[] = "CREATE TABLE {$p}creditos_organizations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NULL,
            organization_id BIGINT UNSIGNED NULL,
            client_type VARCHAR(30) NOT NULL DEFAULT 'personal',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(50) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            KEY organization_id (organization_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_businesses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            legal_name VARCHAR(190) NOT NULL,
            entity_type VARCHAR(80) NULL,
            state_of_formation VARCHAR(80) NULL,
            ein_last4 VARCHAR(4) NULL,
            website VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_onboarding (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            journey VARCHAR(30) NOT NULL DEFAULT 'personal',
            goals LONGTEXT NULL,
            consent_version VARCHAR(40) NULL,
            consented_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_id (client_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_roadmap_progress (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NULL,
            roadmap_type VARCHAR(30) NOT NULL,
            step_number TINYINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'locked',
            percent_complete DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            completed_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY roadmap_step (client_id, business_id, roadmap_type, step_number),
            KEY client_id (client_id),
            KEY business_id (business_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NULL,
            business_id BIGINT UNSIGNED NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            roadmap_type VARCHAR(30) NULL,
            step_number TINYINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            due_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY assigned_user_id (assigned_user_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_disputes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            bureau VARCHAR(80) NULL,
            furnisher VARCHAR(190) NULL,
            title VARCHAR(190) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            current_round SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            response_due_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_dispute_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dispute_id BIGINT UNSIGNED NOT NULL,
            account_label VARCHAR(190) NULL,
            dispute_reason TEXT NULL,
            evidence_notes TEXT NULL,
            outcome VARCHAR(60) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY dispute_id (dispute_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            title VARCHAR(190) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY category (category)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_billing_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
            provider_customer_id VARCHAR(190) NULL,
            plan_key VARCHAR(80) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'inactive',
            renews_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY client_provider (client_id, provider),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            type VARCHAR(60) NOT NULL DEFAULT 'info',
            title VARCHAR(190) NOT NULL,
            message TEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$p}creditos_audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            organization_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            object_type VARCHAR(100) NULL,
            object_id BIGINT UNSIGNED NULL,
            metadata LONGTEXT NULL,
            ip_address VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY client_id (client_id),
            KEY action (action)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        self::install_roles();
        update_option( 'creditos_db_version', CREDITOS_CORE_VERSION );
    }

    public static function install_roles() {
        $client_caps = array(
            'read' => true,
            'creditos_view_own_profile' => true,
            'creditos_manage_own_onboarding' => true,
            'creditos_manage_own_documents' => true,
        );
        $specialist_caps = array_merge( $client_caps, array(
            'creditos_manage_clients' => true,
            'creditos_manage_roadmaps' => true,
            'creditos_manage_tasks' => true,
            'creditos_manage_disputes' => true,
            'creditos_view_staff_dashboard' => true,
        ) );
        $business_caps = array_merge( $specialist_caps, array(
            'creditos_manage_business_credit' => true,
        ) );

        add_role( 'creditos_client', __( 'CreditOS Client', 'creditos' ), $client_caps );
        add_role( 'creditos_specialist', __( 'CreditOS Specialist', 'creditos' ), $specialist_caps );
        add_role( 'creditos_business_specialist', __( 'CreditOS Business Specialist', 'creditos' ), $business_caps );

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( array_keys( $business_caps ) as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
