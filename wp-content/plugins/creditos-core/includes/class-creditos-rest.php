<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreditOS_REST {
    private $repository;

    public function __construct( CreditOS_Repository $repository ) {
        $this->repository = $repository;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'creditos/v1', '/onboarding', array(
            array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_onboarding' ), 'permission_callback' => array( $this, 'must_be_logged_in' ) ),
            array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_onboarding' ), 'permission_callback' => array( $this, 'must_be_logged_in' ) ),
        ) );

        register_rest_route( 'creditos/v1', '/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_dashboard' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );

        register_rest_route( 'creditos/v1', '/roadmaps', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'save_roadmap' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );

        register_rest_route( 'creditos/v1', '/tasks', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'create_task' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );
        register_rest_route( 'creditos/v1', '/tasks/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array( $this, 'update_task' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );

        register_rest_route( 'creditos/v1', '/disputes', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'create_dispute' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );

        register_rest_route( 'creditos/v1', '/documents', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'add_document' ),
            'permission_callback' => array( $this, 'can_manage_own_documents' ),
        ) );

        register_rest_route( 'creditos/v1', '/billing', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'save_billing' ),
            'permission_callback' => array( $this, 'can_manage_billing' ),
        ) );

        register_rest_route( 'creditos/v1', '/staff/clients', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'list_clients' ),
            'permission_callback' => array( $this, 'can_view_staff' ),
        ) );
    }

    public function must_be_logged_in() {
        return is_user_logged_in();
    }

    public function can_view_staff() {
        return current_user_can( 'creditos_view_staff_dashboard' ) || current_user_can( 'manage_options' );
    }

    public function can_manage_own_documents() {
        return is_user_logged_in() && ( current_user_can( 'creditos_manage_own_documents' ) || current_user_can( 'creditos_manage_clients' ) || current_user_can( 'manage_options' ) );
    }

    public function can_manage_billing() {
        return current_user_can( 'manage_options' ) || current_user_can( 'creditos_manage_clients' );
    }

    private function current_client() {
        return $this->repository->get_or_create_client_for_user( get_current_user_id() );
    }

    private function client_or_error() {
        $client = $this->current_client();
        if ( ! $client ) {
            return new WP_Error( 'creditos_client_missing', __( 'CreditOS client profile could not be loaded.', 'creditos' ), array( 'status' => 404 ) );
        }
        return $client;
    }

    public function get_onboarding() {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        return rest_ensure_response( $this->repository->get_onboarding( $client->id ) );
    }

    public function save_onboarding( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $journey = sanitize_text_field( $request->get_param( 'journey' ) );
        $goals = $request->get_param( 'goals' );
        $consented = rest_sanitize_boolean( $request->get_param( 'consented' ) );
        if ( ! is_array( $goals ) ) $goals = array();
        if ( ! $consented ) {
            return new WP_Error( 'creditos_consent_required', __( 'Consent is required to complete onboarding.', 'creditos' ), array( 'status' => 400 ) );
        }
        $saved = $this->repository->save_onboarding( $client->id, $journey, $goals, $consented );
        $this->repository->audit( get_current_user_id(), $client->id, 'onboarding_saved', 'onboarding', $saved['id'] ?? null, array( 'journey' => $journey ) );
        return rest_ensure_response( array( 'success' => true, 'onboarding' => $saved ) );
    }

    public function get_dashboard() {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        return rest_ensure_response( $this->repository->get_dashboard( $client->id, get_current_user_id() ) );
    }

    public function save_roadmap( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $id = $this->repository->save_roadmap_step(
            $client->id,
            sanitize_key( $request->get_param( 'roadmap_type' ) ),
            absint( $request->get_param( 'step_number' ) ),
            sanitize_key( $request->get_param( 'status' ) ),
            (float) $request->get_param( 'percent_complete' ),
            absint( $request->get_param( 'business_id' ) )
        );
        $this->repository->audit( get_current_user_id(), $client->id, 'roadmap_saved', 'roadmap_progress', $id );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function create_task( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $data = $request->get_json_params();
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'creditos_task_title_required', __( 'A task title is required.', 'creditos' ), array( 'status' => 400 ) );
        }
        $assigned = current_user_can( 'creditos_manage_tasks' ) && ! empty( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : get_current_user_id();
        $id = $this->repository->create_task( $client->id, $data, $assigned );
        $this->repository->audit( get_current_user_id(), $client->id, 'task_created', 'task', $id );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function update_task( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $id = absint( $request['id'] );
        $ok = $this->repository->update_task( $client->id, $id, $request->get_json_params() );
        if ( ! $ok ) {
            return new WP_Error( 'creditos_task_not_found', __( 'Task not found.', 'creditos' ), array( 'status' => 404 ) );
        }
        $this->repository->audit( get_current_user_id(), $client->id, 'task_updated', 'task', $id );
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function create_dispute( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $data = $request->get_json_params();
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'creditos_dispute_title_required', __( 'A dispute review title is required.', 'creditos' ), array( 'status' => 400 ) );
        }
        $id = $this->repository->create_dispute( $client->id, $data );
        $this->repository->audit( get_current_user_id(), $client->id, 'dispute_created', 'dispute', $id, array( 'status' => 'draft' ) );
        return rest_ensure_response( array( 'success' => true, 'id' => $id, 'status' => 'draft' ) );
    }

    public function add_document( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $attachment_id = absint( $request->get_param( 'attachment_id' ) );
        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            return new WP_Error( 'creditos_attachment_invalid', __( 'A valid WordPress attachment is required.', 'creditos' ), array( 'status' => 400 ) );
        }
        $attachment = get_post( $attachment_id );
        $is_staff = current_user_can( 'creditos_manage_clients' ) || current_user_can( 'manage_options' );
        if ( ! $is_staff && absint( $attachment->post_author ) !== get_current_user_id() ) {
            return new WP_Error( 'creditos_attachment_forbidden', __( 'You cannot attach that file to this CreditOS account.', 'creditos' ), array( 'status' => 403 ) );
        }
        $id = $this->repository->add_document( $client->id, $attachment_id, sanitize_key( $request->get_param( 'category' ) ), sanitize_text_field( $request->get_param( 'title' ) ) );
        $this->repository->audit( get_current_user_id(), $client->id, 'document_added', 'document', $id );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function save_billing( WP_REST_Request $request ) {
        $client = $this->client_or_error();
        if ( is_wp_error( $client ) ) return $client;
        $id = $this->repository->save_billing_account( $client->id, $request->get_json_params() );
        $this->repository->audit( get_current_user_id(), $client->id, 'billing_record_saved', 'billing_account', $id );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }

    public function list_clients( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'clients' => $this->repository->list_clients( absint( $request->get_param( 'limit' ) ?: 100 ) ) ) );
    }
}
