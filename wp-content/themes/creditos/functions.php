<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function creditos_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array( 'primary' => __( 'Primary Menu', 'creditos' ) ) );
}
add_action( 'after_setup_theme', 'creditos_theme_setup' );

function creditos_ensure_app_pages() {
    $pages = array(
        'dashboard' => array( 'title' => 'CreditOS Dashboard', 'template' => 'page-dashboard.php' ),
        'credit-reports' => array( 'title' => 'CreditOS Credit Reports', 'template' => 'page-credit-reports.php' ),
    );
    foreach ( $pages as $slug => $config ) {
        $page = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( array(
                'post_title' => $config['title'],
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '',
            ) );
            if ( $page_id && ! is_wp_error( $page_id ) ) update_post_meta( $page_id, '_wp_page_template', $config['template'] );
        } elseif ( get_post_meta( $page->ID, '_wp_page_template', true ) !== $config['template'] ) {
            update_post_meta( $page->ID, '_wp_page_template', $config['template'] );
        }
    }
}
add_action( 'after_switch_theme', 'creditos_ensure_app_pages' );
add_action( 'init', 'creditos_ensure_app_pages', 20 );

function creditos_theme_assets() {
    $version = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'creditos-style', get_stylesheet_uri(), array(), $version );

    if ( is_front_page() ) {
        wp_enqueue_style( 'creditos-front', get_template_directory_uri() . '/assets/css/front.css', array( 'creditos-style' ), $version );
        wp_enqueue_script( 'creditos-front', get_template_directory_uri() . '/assets/js/front.js', array(), $version, true );
        wp_localize_script( 'creditos-front', 'CreditOSConfig', creditos_frontend_config() );
    }

    if ( is_page( 'dashboard' ) || is_page_template( 'page-dashboard.php' ) ) {
        wp_enqueue_style( 'creditos-dashboard', get_template_directory_uri() . '/assets/css/dashboard.css', array( 'creditos-style' ), $version );
        wp_enqueue_script( 'creditos-dashboard', get_template_directory_uri() . '/assets/js/dashboard.js', array(), $version, true );
        wp_enqueue_script( 'creditos-layout-polish', get_template_directory_uri() . '/assets/js/layout-polish.js', array( 'creditos-dashboard' ), $version, true );
        wp_localize_script( 'creditos-dashboard', 'CreditOSConfig', creditos_frontend_config() );
    }

    if ( is_page( 'credit-reports' ) || is_page_template( 'page-credit-reports.php' ) ) {
        wp_enqueue_style( 'creditos-reports', get_template_directory_uri() . '/assets/css/reports.css', array( 'creditos-style' ), $version );
        wp_enqueue_style( 'creditos-report-connections', get_template_directory_uri() . '/assets/css/report-connections.css', array( 'creditos-reports' ), $version );
        wp_enqueue_script( 'creditos-reports', get_template_directory_uri() . '/assets/js/reports.js', array(), $version, true );
        wp_localize_script( 'creditos-reports', 'CreditOSConfig', creditos_frontend_config() );
    }

    if ( is_front_page() || is_page( 'dashboard' ) || is_page_template( 'page-dashboard.php' ) || is_page( 'credit-reports' ) || is_page_template( 'page-credit-reports.php' ) ) {
        wp_enqueue_style( 'creditos-typography', get_template_directory_uri() . '/assets/css/typography.css', array( 'creditos-style' ), $version );
        if ( is_front_page() || is_page( 'dashboard' ) || is_page_template( 'page-dashboard.php' ) ) {
            wp_enqueue_style( 'creditos-layout-polish', get_template_directory_uri() . '/assets/css/layout-polish.css', array( 'creditos-typography' ), $version );
        }
        $green_dependencies = array( 'creditos-typography' );
        if ( is_page( 'credit-reports' ) || is_page_template( 'page-credit-reports.php' ) ) $green_dependencies[] = 'creditos-report-connections';
        if ( is_front_page() || is_page( 'dashboard' ) || is_page_template( 'page-dashboard.php' ) ) $green_dependencies[] = 'creditos-layout-polish';
        wp_enqueue_style( 'creditos-green-brand', get_template_directory_uri() . '/assets/css/creditos-green.css', $green_dependencies, $version );
    }
}
add_action( 'wp_enqueue_scripts', 'creditos_theme_assets' );

function creditos_frontend_config() {
    return array(
        'restUrl' => esc_url_raw( rest_url( 'creditos/v1/' ) ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'loggedIn' => is_user_logged_in(),
        'dashboardUrl' => esc_url_raw( home_url( '/dashboard/' ) ),
        'reportImportUrl' => esc_url_raw( home_url( '/credit-reports/' ) ),
        'loginUrl' => esc_url_raw( wp_login_url( home_url( '/dashboard/' ) ) ),
        'registerUrl' => esc_url_raw( wp_registration_url() ),
        'userName' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
        'canStaff' => current_user_can( 'creditos_view_staff_dashboard' ) || current_user_can( 'manage_options' ),
    );
}
