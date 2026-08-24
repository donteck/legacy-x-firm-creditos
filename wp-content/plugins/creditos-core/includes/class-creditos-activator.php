<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_Activator {

    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array();

        $tables[] = "CREATE TABLE {$wpdb->prefix}creditos_clients (
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
            KEY wp_user_id (wp_user_id),
            KEY organization_id (organization_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}creditos_businesses (
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

        $tables[] = "CREATE TABLE {$wpdb->prefix}creditos_roadmap_progress (
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

        $tables[] = "CREATE TABLE {$wpdb->prefix}creditos_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NULL,
            business_id BIGINT UNSIGNED NULL,
            assigned_user_id BIGINT UNSIGNED NULL,
            roadmap_type VARCHAR(30) NULL,
            step_number TINYINT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            due_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY assigned_user_id (assigned_user_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}creditos_audit_logs (
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

        update_option( 'creditos_db_version', CREDITOS_CORE_VERSION );
    }
}
