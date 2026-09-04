<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CreditOS Credit Data Connection Gateway.
 *
 * Provides a provider-neutral API for starting authorized consumer credit-data
 * connections. Concrete bureau/provider adapters are intentionally configured
 * outside source control and can hook into the filters documented below.
 */
class CreditOS_Credit_Connections {
    private $repository;
    private $wpdb;

    public function __construct( CreditOS_Repository $repository ) {
        global $wpdb;
        $this->repository = $repository;
        $this->wpdb = $wpdb;
        add_action( 'init', array( $this, 'maybe_install_schema' ), 6 );
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function maybe_install_schema() {
        if ( get_option( 'creditos_connections_schema_version' ) === CREDITOS_CORE_VERSION ) return;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $this->wpdb->prefix . 'creditos_credit_connections';
        $c = $this->wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(80) NOT NULL,
            bureau VARCHAR(40) NOT NULL,
            provider_connection_id VARCHAR(190) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            consented_at DATETIME NULL,
            connected_at DATETIME NULL,
            last_synced_at DATETIME NULL,
            disconnected_at DATETIME NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY provider (provider),
            KEY bureau (bureau),
            KEY status (status)
        ) $c;" );
        update_option( 'creditos_connections_schema_version', CREDITOS_CORE_VERSION );
    }

    public function register_routes() {
        register_rest_route( 'creditos/v1', '/credit-connections', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( $this, 'list_connections' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
        register_rest_route( 'creditos/v1', '/credit-connections/(?P<provider>[a-z0-9_-]+)/start', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'start_connection' ),
            'permission_callback' => array( $this, 'logged_in' ),
        ) );
    }

    public function logged_in() { return is_user_logged_in(); }

    private function current_client() {
        return $this->repository->get_or_create_client_for_user( get_current_user_id() );
    }

    private function consent_is_valid( $client_id ) {
        $table = $this->wpdb->prefix . 'creditos_onboarding';
        return (bool) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE client_id=%d AND consented_at IS NOT NULL LIMIT 1", absint( $client_id ) ) );
    }

    private function providers() {
        $providers = array(
            'experian' => array(
                'key' => 'experian',
                'name' => 'Experian',
                'bureau' => 'experian',
                'description' => 'Connect an authorized Experian-compatible data provider when CreditOS production credentials and permissions are configured.',
                'configured' => false,
            ),
            'equifax' => array(
                'key' => 'equifax',
                'name' => 'Equifax',
                'bureau' => 'equifax',
                'description' => 'Connect an authorized Equifax-compatible consumer credit-data service when production access is configured.',
                'configured' => false,
            ),
            'transunion' => array(
                'key' => 'transunion',
                'name' => 'TransUnion',
                'bureau' => 'transunion',
                'description' => 'Connect an authorized TransUnion-compatible data provider when CreditOS production credentials and permissions are configured.',
                'configured' => false,
            ),
            'multi_bureau' => array(
                'key' => 'multi_bureau',
                'name' => '3-Bureau Connection',
                'bureau' => 'multi',
                'description' => 'Use one approved multi-bureau provider to import Experian, Equifax, and TransUnion data through a single authorized connection.',
                'configured' => false,
            ),
        );
        return apply_filters( 'creditos_credit_connection_providers', $providers );
    }

    public function list_connections() {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );

        $table = $this->wpdb->prefix . 'creditos_credit_connections';
        $existing = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT id,provider,bureau,status,connected_at,last_synced_at,disconnected_at FROM {$table} WHERE client_id=%d ORDER BY updated_at DESC",
            absint( $client->id )
        ), ARRAY_A );

        $providers = array_values( $this->providers() );
        foreach ( $providers as &$provider ) {
            $provider['configured'] = (bool) $provider['configured'];
            $provider['status'] = $provider['configured'] ? 'ready' : 'provider_setup_required';
            foreach ( $existing as $row ) {
                if ( $row['provider'] === $provider['key'] && ! in_array( $row['status'], array( 'disconnected','failed' ), true ) ) {
                    $provider['status'] = $row['status'];
                    $provider['connection'] = $row;
                    break;
                }
            }
        }
        unset( $provider );

        return rest_ensure_response( array(
            'providers' => $providers,
            'connections' => $existing,
            'consent_valid' => $this->consent_is_valid( $client->id ),
        ) );
    }

    public function start_connection( WP_REST_Request $request ) {
        $client = $this->current_client();
        if ( ! $client ) return new WP_Error( 'creditos_client_missing', 'CreditOS client profile could not be loaded.', array( 'status' => 404 ) );
        if ( ! $this->consent_is_valid( $client->id ) ) {
            return new WP_Error( 'creditos_connection_consent_required', 'Complete CreditOS onboarding and consent before connecting credit data.', array( 'status' => 403 ) );
        }

        $key = sanitize_key( $request['provider'] );
        $providers = $this->providers();
        if ( ! isset( $providers[ $key ] ) ) return new WP_Error( 'creditos_provider_unknown', 'That credit-data provider is not registered in CreditOS.', array( 'status' => 404 ) );
        $provider = $providers[ $key ];

        if ( empty( $provider['configured'] ) ) {
            $this->repository->audit( get_current_user_id(), $client->id, 'credit_connection_requested', 'credit_provider', null, array( 'provider' => $key, 'bureau' => $provider['bureau'] ) );
            return new WP_Error(
                'creditos_provider_setup_required',
                sprintf( '%s connection is built into the CreditOS gateway, but production provider approval and credentials must be configured before live data can be requested.', $provider['name'] ),
                array( 'status' => 503, 'provider' => $key )
            );
        }

        /**
         * Concrete adapters should return either WP_Error or an array containing:
         * authorization_url, provider_connection_id, status, metadata.
         */
        $result = apply_filters( 'creditos_start_credit_connection', null, $provider, $client, $request );
        if ( is_wp_error( $result ) ) return $result;
        if ( ! is_array( $result ) || empty( $result['authorization_url'] ) ) {
            return new WP_Error( 'creditos_provider_adapter_missing', 'The provider is configured but its authorization adapter did not return a valid connection URL.', array( 'status' => 503 ) );
        }

        $now = current_time( 'mysql' );
        $this->wpdb->insert( $this->wpdb->prefix . 'creditos_credit_connections', array(
            'client_id' => absint( $client->id ),
            'provider' => $key,
            'bureau' => sanitize_key( $provider['bureau'] ),
            'provider_connection_id' => sanitize_text_field( $result['provider_connection_id'] ?? '' ),
            'status' => sanitize_key( $result['status'] ?? 'authorization_pending' ),
            'consented_at' => $now,
            'metadata' => wp_json_encode( $result['metadata'] ?? array() ),
            'created_at' => $now,
            'updated_at' => $now,
        ) );
        $connection_id = absint( $this->wpdb->insert_id );
        $this->repository->audit( get_current_user_id(), $client->id, 'credit_connection_started', 'credit_connection', $connection_id, array( 'provider' => $key ) );

        return rest_ensure_response( array(
            'success' => true,
            'connection_id' => $connection_id,
            'authorization_url' => esc_url_raw( $result['authorization_url'] ),
            'status' => sanitize_key( $result['status'] ?? 'authorization_pending' ),
        ) );
    }
}
