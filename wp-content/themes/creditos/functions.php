<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function creditos_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'creditos_theme_setup' );

function creditos_enqueue_assets() {
    wp_enqueue_style(
        'creditos-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'creditos_enqueue_assets' );
