<?php
/**
 * Disable WordPress Features
 * Disable comments, emojis, RSS, XML-RPC, etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Disable_Features {

    private static $instance = null;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'wac_settings', array() );
        
        // Disable Comments
        if ( ! empty( $this->options['disable_comments'] ) ) {
            $this->disable_comments();
        }
        
        // Disable Emojis
        if ( ! empty( $this->options['disable_emojis'] ) ) {
            $this->disable_emojis();
        }
        
        // Disable RSS
        if ( ! empty( $this->options['disable_rss'] ) ) {
            $this->disable_rss();
        }
        
        // Disable XML-RPC
        if ( ! empty( $this->options['disable_xmlrpc'] ) ) {
            $this->disable_xmlrpc();
        }
        
        // Remove WP Version
        if ( ! empty( $this->options['remove_wp_version'] ) ) {
            $this->remove_wp_version();
        }
        
        // Disable REST API for non-logged users
        if ( ! empty( $this->options['disable_rest_api'] ) ) {
            $this->disable_rest_api();
        }
        
        // Disable Gutenberg
        if ( ! empty( $this->options['disable_gutenberg'] ) ) {
            $this->disable_gutenberg();
        }
    }

    /**
     * Disable comments completely
     */
    private function disable_comments() {
        // Close comments on the front-end
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );
        
        // Hide existing comments
        add_filter( 'comments_array', '__return_empty_array', 10, 2 );
        
        // Remove comments page in menu
        add_action( 'admin_menu', function() {
            remove_menu_page( 'edit-comments.php' );
        });
        
        // Remove comments links from admin bar
        add_action( 'init', function() {
            if ( is_admin_bar_showing() ) {
                remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
            }
        });
        
        // Remove comments from post types
        add_action( 'admin_init', function() {
            $post_types = get_post_types();
            foreach ( $post_types as $post_type ) {
                if ( post_type_supports( $post_type, 'comments' ) ) {
                    remove_post_type_support( $post_type, 'comments' );
                    remove_post_type_support( $post_type, 'trackbacks' );
                }
            }
        });
        
        // Remove comments metabox from dashboard
        add_action( 'admin_init', function() {
            remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
        });
    }

    /**
     * Disable WordPress emojis
     */
    private function disable_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        
        add_filter( 'tiny_mce_plugins', function( $plugins ) {
            if ( is_array( $plugins ) ) {
                return array_diff( $plugins, array( 'wpemoji' ) );
            }
            return array();
        });
        
        add_filter( 'wp_resource_hints', function( $urls, $relation_type ) {
            if ( 'dns-prefetch' === $relation_type ) {
                $urls = array_filter( $urls, function( $url ) {
                    return strpos( $url, 'https://s.w.org/images/core/emoji/' ) === false;
                });
            }
            return $urls;
        }, 10, 2 );
    }

    /**
     * Disable RSS feeds
     */
    private function disable_rss() {
        add_action( 'do_feed', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rdf', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rss', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rss2', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_atom', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_rss2_comments', array( $this, 'disable_feed' ), 1 );
        add_action( 'do_feed_atom_comments', array( $this, 'disable_feed' ), 1 );
        
        // Remove feed links from head
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
    }

    /**
     * Redirect feed to homepage
     */
    public function disable_feed() {
        wp_redirect( home_url(), 301 );
        exit;
    }

    /**
     * Disable XML-RPC
     */
    private function disable_xmlrpc() {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'wp_headers', function( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        });
        
        // Remove RSD link
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
    }

    /**
     * Remove WordPress version from frontend
     */
    private function remove_wp_version() {
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );
        
        // Remove version from scripts and styles
        add_filter( 'style_loader_src', array( $this, 'remove_version_strings' ), 9999 );
        add_filter( 'script_loader_src', array( $this, 'remove_version_strings' ), 9999 );
    }

    /**
     * Remove version strings from assets
     */
    public function remove_version_strings( $src ) {
        if ( strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    /**
     * Disable REST API for non-logged users
     */
    private function disable_rest_api() {
        add_filter( 'rest_authentication_errors', function( $result ) {
            if ( ! is_user_logged_in() ) {
                return new WP_Error( 
                    'rest_disabled', 
                    __( 'REST API is disabled for non-authenticated users.', 'devforge-admin-cleaner' ), 
                    array( 'status' => 401 ) 
                );
            }
            return $result;
        });
    }

    /**
     * Disable Gutenberg editor
     */
    private function disable_gutenberg() {
        // Disable for posts
        add_filter( 'use_block_editor_for_post', '__return_false', 10 );
        
        // Disable for post types
        add_filter( 'use_block_editor_for_post_type', '__return_false', 10 );
        
        // Remove Gutenberg styles (but keep global-styles as it contains theme styles)
        add_action( 'wp_enqueue_scripts', function() {
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wp-block-library-theme' );
            wp_dequeue_style( 'wc-blocks-style' );
            // DO NOT remove 'global-styles' - it contains theme CSS and is required for frontend styling
        }, 100 );
    }

    /**
     * Get all available features to disable
     */
    public static function get_features() {
        return array(
            'disable_comments' => array(
                'label' => __( 'Disable Comments', 'devforge-admin-cleaner' ),
                'description' => __( 'Completely disable comments system', 'devforge-admin-cleaner' ),
            ),
            'disable_emojis' => array(
                'label' => __( 'Disable Emojis', 'devforge-admin-cleaner' ),
                'description' => __( 'Remove emoji scripts for faster loading', 'devforge-admin-cleaner' ),
            ),
            'disable_rss' => array(
                'label' => __( 'Disable RSS Feeds', 'devforge-admin-cleaner' ),
                'description' => __( 'Disable all RSS feed endpoints', 'devforge-admin-cleaner' ),
            ),
            'disable_xmlrpc' => array(
                'label' => __( 'Disable XML-RPC', 'devforge-admin-cleaner' ),
                'description' => __( 'Disable XML-RPC (recommended for security)', 'devforge-admin-cleaner' ),
            ),
            'disable_rest_api' => array(
                'label' => __( 'Restrict REST API', 'devforge-admin-cleaner' ),
                'description' => __( 'Require authentication for REST API access', 'devforge-admin-cleaner' ),
            ),
            'disable_gutenberg' => array(
                'label' => __( 'Disable Gutenberg', 'devforge-admin-cleaner' ),
                'description' => __( 'Use classic editor instead of block editor', 'devforge-admin-cleaner' ),
            ),
            'remove_wp_version' => array(
                'label' => __( 'Hide WordPress Version', 'devforge-admin-cleaner' ),
                'description' => __( 'Remove version number from frontend (security)', 'devforge-admin-cleaner' ),
            ),
        );
    }
}

