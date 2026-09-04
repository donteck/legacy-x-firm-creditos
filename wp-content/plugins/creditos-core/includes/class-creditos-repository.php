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
        $tasks = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,title,description,status,priority,due_at,completed_at FROM {$p}creditos_tasks WHERE client_id = %d ORDER BY FIELD(status,'open','in_progress','done'), due_at IS NULL, due_at ASC LIMIT 20", $client_id ), ARRAY_A );
        $disputes = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,title,bureau,furnisher,status,current_round,response_due_at FROM {$p}creditos_disputes WHERE client_id = %d ORDER BY updated_at DESC LIMIT 20", $client_id ), ARRAY_A );
        $documents = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$p}creditos_documents WHERE client_id = %d AND status = 'active'", $client_id ) );
        $notifications = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT id,type,title,message,is_read,created_at FROM {$p}creditos_notifications WHERE user_id = %d ORDER BY created_at DESC LIMIT 10", absint( $user_id ) ), ARRAY_A );
        $roadmap = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT roadmap_type,step_number,status,percent_complete,completed_at FROM {$p}creditos_roadmap_progress WHERE client_id = %d ORDER BY roadmap_type,step_number", $client_id ), ARRAY_A );
        $billing = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT provider,provider_customer_id,plan_key,status,renews_at FROM {$p}creditos_billing_accounts WHERE client_id = %d LIMIT 1", $client_id ), ARRAY_A );
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

    public function save_roadmap_step( $client_id, $roadmap_type, $step_number, $status, $percent_complete, $business_id = null ) {
        $table = $this->wpdb->prefix . 'creditos_roadmap_progress';
        $client_id = absint( $client_id );
        $business_id = $business_id ? absint( $business_id ) : 0;
        $roadmap_type = in_array( $roadmap_type, array( 'personal', 'business' ), true ) ? $roadmap_type : 'personal';
        $step_number = min( 7, max( 1, absint( $step_number ) ) );
        $status = in_array( $status, array( 'locked', 'available', 'active', 'complete' ), true ) ? $status : 'active';
        $percent_complete = min( 100, max( 0, (float) $percent_complete ) );
        $now = current_time( 'mysql' );
        $existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE client_id=%d AND business_id=%d AND roadmap_type=%s AND step_number=%d", $client_id, $business_id, $roadmap_type, $step_number ) );
        $data = array(
            'client_id' => $client_id,
            'business_id' => $business_id,
            'roadmap_type' => $roadmap_type,
            'step_number' => $step_number,
            'status' => $status,
            'percent_complete' => $percent_complete,
            'completed_at' => 'complete' === $status ? $now : null,
            'updated_at' => $now,
        );
        if ( $existing ) {
            $this->wpdb->update( $table, $data, array( 'id' => $existing ) );
            return absint( $existing );
        }
        $this->wpdb->insert( $table, $data );
        return absint( $this->wpdb->insert_id );
    }

    public function create_task( $client_id, array $data, $assigned_user_id = null ) {
        $table = $this->wpdb->prefix . 'creditos_tasks';
        $now = current_time( 'mysql' );
        $this->wpdb->insert( $table, array(
            'client_id' => absint( $client_id ),
            'business_id' => ! empty( $data['business_id'] ) ? absint( $data['business_id'] ) : null,
            'assigned_user_id' => $assigned_user_id ? absint( $assigned_user_id ) : null,
            'roadmap_type' => ! empty( $data['roadmap_type'] ) ? sanitize_key( $data['roadmap_type'] ) : null,
            'step_number' => ! empty( $data['step_number'] ) ? absint( $data['step_number'] ) : null,
            'title' => sanitize_text_field( $data['title'] ?? '' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'status' => 'open',
            'priority' => in_array( $data['priority'] ?? 'normal', array( 'low', 'normal', 'high', 'urgent' ), true ) ? $data['priority'] : 'normal',
            'due_at' => ! empty( $data['due_at'] ) ? sanitize_text_field( $data['due_at'] ) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ) );
        return absint( $this->wpdb->insert_id );
    }

    public function update_task( $client_id, $task_id, array $data ) {
        $table = $this->wpdb->prefix . 'creditos_tasks';
        $task_id = absint( $task_id );
        $exists = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d AND client_id=%d", $task_id, absint( $client_id ) ) );
        if ( ! $exists ) {
            return false;
        }
        $update = array( 'updated_at' => current_time( 'mysql' ) );
        if ( isset( $data['status'] ) && in_array( $data['status'], array( 'open', 'in_progress', 'done' ), true ) ) {
            $update['status'] = $data['status'];
            $update['completed_at'] = 'done' === $data['status'] ? current_time( 'mysql' ) : null;
        }
        if ( isset( $data['title'] ) ) $update['title'] = sanitize_text_field( $data['title'] );
        if ( isset( $data['description'] ) ) $update['description'] = sanitize_textarea_field( $data['description'] );
        if ( isset( $data['priority'] ) && in_array( $data['priority'], array( 'low', 'normal', 'high', 'urgent' ), true ) ) $update['priority'] = $data['priority'];
        if ( array_key_exists( 'due_at', $data ) ) $update['due_at'] = $data['due_at'] ? sanitize_text_field( $data['due_at'] ) : null;
        return false !== $this->wpdb->update( $table, $update, array( 'id' => $task_id, 'client_id' => absint( $client_id ) ) );
    }

    public function create_dispute( $client_id, array $data ) {
        $table = $this->wpdb->prefix . 'creditos_disputes';
        $now = current_time( 'mysql' );
        $this->wpdb->insert( $table, array(
            'client_id' => absint( $client_id ),
            'bureau' => sanitize_text_field( $data['bureau'] ?? '' ),
            'furnisher' => sanitize_text_field( $data['furnisher'] ?? '' ),
            'title' => sanitize_text_field( $data['title'] ?? 'Dispute review' ),
            'status' => 'draft',
            'current_round' => 1,
            'response_due_at' => ! empty( $data['response_due_at'] ) ? sanitize_text_field( $data['response_due_at'] ) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ) );
        return absint( $this->wpdb->insert_id );
    }

    public function add_document( $client_id, $attachment_id, $category, $title ) {
        $this->wpdb->insert( $this->wpdb->prefix . 'creditos_documents', array(
            'client_id' => absint( $client_id ),
            'attachment_id' => absint( $attachment_id ),
            'category' => sanitize_key( $category ?: 'general' ),
            'title' => sanitize_text_field( $title ),
            'status' => 'active',
            'created_at' => current_time( 'mysql' ),
        ) );
        return absint( $this->wpdb->insert_id );
    }

    public function save_billing_account( $client_id, array $data ) {
        $table = $this->wpdb->prefix . 'creditos_billing_accounts';
        $provider = sanitize_key( $data['provider'] ?? 'stripe' );
        $existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE client_id=%d AND provider=%s", absint( $client_id ), $provider ) );
        $row = array(
            'client_id' => absint( $client_id ),
            'provider' => $provider,
            'provider_customer_id' => sanitize_text_field( $data['provider_customer_id'] ?? '' ),
            'plan_key' => sanitize_key( $data['plan_key'] ?? '' ),
            'status' => sanitize_key( $data['status'] ?? 'inactive' ),
            'renews_at' => ! empty( $data['renews_at'] ) ? sanitize_text_field( $data['renews_at'] ) : null,
            'updated_at' => current_time( 'mysql' ),
        );
        if ( $existing ) {
            $this->wpdb->update( $table, $row, array( 'id' => $existing ) );
            return absint( $existing );
        }
        $row['created_at'] = current_time( 'mysql' );
        $this->wpdb->insert( $table, $row );
        return absint( $this->wpdb->insert_id );
    }

    public function list_clients( $limit = 100 ) {
        $limit = min( 200, max( 1, absint( $limit ) ) );
        return $this->wpdb->get_results( "SELECT id,wp_user_id,client_type,status,first_name,last_name,email,phone,created_at,updated_at FROM {$this->wpdb->prefix}creditos_clients ORDER BY updated_at DESC LIMIT {$limit}", ARRAY_A );
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
