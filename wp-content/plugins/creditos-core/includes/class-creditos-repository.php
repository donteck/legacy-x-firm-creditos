<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_Repository {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function get_or_create_client_for_user( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return null;
        }

        $table = $this->wpdb->prefix . 'creditos_clients';
        $client = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d LIMIT 1", $user_id ) );
        if ( $client ) {
            return $client;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return null;
        }

        $this->wpdb->insert(
            $table,
            array(
                'wp_user_id' => $user_id,
                'client_type' => 'personal',
                'status' => 'active',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->user_email,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $this->wpdb->insert_id ) );
    }

    public function save_onboarding( $client_id, $journey, array $goals, $consented ) {
        $client_id = absint( $client_id );
        $journey = in_array( $journey, array( 'Personal', 'Business', 'Combined' ), true ) ? strtolower( $journey ) : 'personal';
        $table = $this->wpdb->prefix . 'creditos_onboarding';
        $now = current_time( 'mysql' );
        $data = array(
            'client_id' => $client_id,
            'journey' => $journey,
            'goals' => wp_json_encode( array_values( array_map( 'sanitize_text_field', $goals ) ) ),
            'consent_version' => $consented ? 'v1' : null,
            'consented_at' => $consented ? $now : null,
            'completed_at' => $consented ? $now : null,
            'updated_at' => $now,
        );

        $existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE client_id = %d", $client_id ) );
        if ( $existing ) {
            $this->wpdb->update( $table, $data, array( 'client_id' => $client_id ) );
        } else {
            $data['created_at'] = $now;
            $this->wpdb->insert( $table, $data );
        }

        $this->wpdb->update(
            $this->wpdb->prefix . 'creditos_clients',
            array( 'client_type' => $journey, 'updated_at' => $now ),
            array( 'id' => $client_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return $this->get_onboarding( $client_id );
    }

    public function get_onboarding( $client_id ) {
        $table = $this->wpdb->prefix . 'creditos_onboarding';
        $row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %d LIMIT 1", absint( $client_id ) ), ARRAY_A );
        if ( $row && ! empty( $row['goals'] ) ) {
            $row['goals'] = json_decode( $row['goals'], true ) ?: array();
        }
        return $row;
    }

    public function get_dashboard( $client_id, $user_id ) {
        $p = $this->wpdb->prefix;
        $client_id = absint( $client_id );
        $tasks = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,title,status,priority,due_at FROM {$p}creditos_tasks WHERE client_id = %d ORDER BY FIELD(status,'open','in_progress','done'), due_at IS NULL, due_at ASC LIMIT 8", $client_id ), ARRAY_A );
        $disputes = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,title,bureau,furnisher,status,current_round,response_due_at FROM {$p}creditos_disputes WHERE client_id = %d ORDER BY updated_at DESC LIMIT 8", $client_id ), ARRAY_A );
        $documents = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$p}creditos_documents WHERE client_id = %d AND status = 'active'", $client_id ) );
        $notifications = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,type,title,message,is_read,created_at FROM {$p}creditos_notifications WHERE user_id = %d ORDER BY created_at DESC LIMIT 10", absint( $user_id ) ), ARRAY_A );
        $roadmap = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT roadmap_type,step_number,status,percent_complete,completed_at FROM {$p}creditos_roadmap_progress WHERE client_id = %d ORDER BY roadmap_type,step_number", $client_id ), ARRAY_A );
        $billing = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT provider,plan_key,status,renews_at FROM {$p}creditos_billing_accounts WHERE client_id = %d LIMIT 1", $client_id ), ARRAY_A );

        return array(
            'onboarding' => $this->get_onboarding( $client_id ),
            'tasks' => $tasks,
            'disputes' => $disputes,
            'documents_count' => $documents,
            'notifications' => $notifications,
            'roadmap' => $roadmap,
            'billing' => $billing,
        );
    }

    public function audit( $user_id, $client_id, $action, $object_type = null, $object_id = null, array $metadata = array() ) {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'creditos_audit_logs',
            array(
                'user_id' => absint( $user_id ),
                'client_id' => absint( $client_id ),
                'action' => sanitize_key( $action ),
                'object_type' => $object_type ? sanitize_key( $object_type ) : null,
                'object_id' => $object_id ? absint( $object_id ) : null,
                'metadata' => wp_json_encode( $metadata ),
                'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
                'created_at' => current_time( 'mysql' ),
            )
        );
    }
}
