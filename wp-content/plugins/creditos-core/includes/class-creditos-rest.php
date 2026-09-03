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
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_onboarding' ),
                'permission_callback' => array( $this, 'must_be_logged_in' ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'save_onboarding' ),
                'permission_callback' => array( $this, 'must_be_logged_in' ),
            ),
        ) );

        register_rest_route( 'creditos/v1', '/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_dashboard' ),
            'permission_callback' => array( $this, 'must_be_logged_in' ),
        ) );
    }

    public function must_be_logged_in() {
        return is_user_logged_in();
    }

    private function current_client() {
        return $this->repository->get_or_create_client_for_user( get_current_user_id() );
    }

    public function get_onboarding() {
        $client = $this->current_client();
        if ( ! $client ) {
            return new WP_Error( 'creditos_client_missing', __( 'CreditOS client profile could not be loaded.', 'creditos' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->repository->get_onboarding( $client->id ) );
    }

    public function save_onboarding( WP_REST_Request $request ) {
        $client = $this->current_client();
        if ( ! $client ) {
            return new WP_Error( 'creditos_client_missing', __( 'CreditOS client profile could not be loaded.', 'creditos' ), array( 'status' => 404 ) );
        }

        $journey = sanitize_text_field( $request->get_param( 'journey' ) );
        $goals = $request->get_param( 'goals' );
        $consented = rest_sanitize_boolean( $request->get_param( 'consented' ) );
        if ( ! is_array( $goals ) ) {
            $goals = array();
        }
        if ( ! $consented ) {
            return new WP_Error( 'creditos_consent_required', __( 'Consent is required to complete onboarding.', 'creditos' ), array( 'status' => 400 ) );
        }

        $saved = $this->repository->save_onboarding( $client->id, $journey, $goals, $consented );
        $this->repository->audit( get_current_user_id(), $client->id, 'onboarding_saved', 'onboarding', $saved['id'] ?? null, array( 'journey' => $journey ) );
        return rest_ensure_response( array( 'success' => true, 'onboarding' => $saved ) );
    }

    public function get_dashboard() {
        $client = $this->current_client();
        if ( ! $client ) {
            return new WP_Error( 'creditos_client_missing', __( 'CreditOS client profile could not be loaded.', 'creditos' ), array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->repository->get_dashboard( $client->id, get_current_user_id() ) );
    }
}
