<?php
/**
 * Duplicate Post
 * One-click duplication for posts, pages, and custom post types
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Duplicate_Post {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $options;

    private function __construct() {
        $this->options = get_option( 'wac_settings', array() );
        
        // Check if feature is enabled (default to enabled)
        if ( isset( $this->options['duplicate_enabled'] ) && empty( $this->options['duplicate_enabled'] ) ) {
            return;
        }
        
        // Add duplicate link to post row actions
        add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
        
        // Handle duplicate action
        add_action( 'admin_action_wac_duplicate_post', array( $this, 'duplicate_post' ) );
        
        // Add duplicate to admin bar when editing (if enabled)
        if ( ! isset( $this->options['duplicate_admin_bar'] ) || ! empty( $this->options['duplicate_admin_bar'] ) ) {
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_duplicate' ), 100 );
        }
        
        // Add bulk action (if enabled)
        if ( ! isset( $this->options['duplicate_bulk'] ) || ! empty( $this->options['duplicate_bulk'] ) ) {
            add_filter( 'bulk_actions-edit-post', array( $this, 'add_bulk_action' ) );
            add_filter( 'bulk_actions-edit-page', array( $this, 'add_bulk_action' ) );
            add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handle_bulk_action' ), 10, 3 );
            add_filter( 'handle_bulk_actions-edit-page', array( $this, 'handle_bulk_action' ), 10, 3 );
        }
        
        // Admin notice
        add_action( 'admin_notices', array( $this, 'duplicate_notice' ) );
    }

    /**
     * Add duplicate link to row actions
     */
    public function add_duplicate_link( $actions, $post ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url( 'admin.php?action=wac_duplicate_post&post=' . $post->ID ),
            'wac_duplicate_' . $post->ID
        );

        $actions['duplicate'] = sprintf(
            '<a href="%s" title="%s" style="color:#007aff">%s</a>',
            esc_url( $url ),
            esc_attr__( 'Duplicate this item', 'webtapot-admin-cleaner' ),
            __( 'Duplicate', 'webtapot-admin-cleaner' )
        );

        return $actions;
    }

    /**
     * Add duplicate to admin bar
     */
    public function admin_bar_duplicate( $wp_admin_bar ) {
        global $post;
        
        if ( ! is_admin() || ! isset( $post ) || ! is_object( $post ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $url = wp_nonce_url(
            admin_url( 'admin.php?action=wac_duplicate_post&post=' . $post->ID ),
            'wac_duplicate_' . $post->ID
        );

        $wp_admin_bar->add_node( array(
            'id'    => 'wac-duplicate-post',
            'title' => '<span class="ab-icon dashicons dashicons-admin-page"></span>' . __( 'Duplicate', 'webtapot-admin-cleaner' ),
            'href'  => $url,
            'meta'  => array(
                'title' => __( 'Duplicate this post', 'webtapot-admin-cleaner' ),
            ),
        ) );
    }

    /**
     * Add bulk duplicate action
     */
    public function add_bulk_action( $actions ) {
        $actions['wac_duplicate'] = __( 'Duplicate', 'webtapot-admin-cleaner' );
        return $actions;
    }

    /**
     * Handle bulk duplicate
     */
    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        if ( $action !== 'wac_duplicate' ) {
            return $redirect_to;
        }

        $duplicated = 0;
        foreach ( $post_ids as $post_id ) {
            if ( $this->create_duplicate( $post_id ) ) {
                $duplicated++;
            }
        }

        return add_query_arg( 'wac_duplicated', $duplicated, $redirect_to );
    }

    /**
     * Duplicate post action
     */
    public function duplicate_post() {
        if ( ! isset( $_GET['post'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            wp_die( 'Invalid request' );
        }

        $post_id = intval( $_GET['post'] );
        
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wac_duplicate_' . $post_id ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'You do not have permission to duplicate posts' );
        }

        $new_id = $this->create_duplicate( $post_id );

        if ( $new_id ) {
            wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_id . '&wac_duplicated=1' ) );
            exit;
        } else {
            wp_die( 'Failed to duplicate post' );
        }
    }

    /**
     * Create duplicate of a post
     */
    private function create_duplicate( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return false;
        }

        $current_user = wp_get_current_user();

        // Create new post data
        $args = array(
            'post_author'    => $current_user->ID,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_name'      => $post->post_name . '-copy',
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => 'draft',
            'post_title'     => $post->post_title . ' (Copy)',
            'post_type'      => $post->post_type,
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
        );

        // Insert new post
        $new_id = wp_insert_post( $args );

        if ( is_wp_error( $new_id ) ) {
            return false;
        }

        // Copy taxonomies
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
            wp_set_object_terms( $new_id, $terms, $taxonomy );
        }

        // Copy post meta
        $meta = get_post_meta( $post_id );
        foreach ( $meta as $key => $values ) {
            // Skip internal meta
            if ( $key[0] === '_' && ! in_array( $key, array( '_thumbnail_id', '_wp_page_template' ) ) ) {
                continue;
            }
            foreach ( $values as $value ) {
                add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
            }
        }

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $new_id, $thumbnail_id );
        }

        return $new_id;
    }

    /**
     * Show admin notice after duplication
     */
    public function duplicate_notice() {
        if ( isset( $_GET['wac_duplicated'] ) ) {
            $count = intval( $_GET['wac_duplicated'] );
            if ( $count === 1 ) {
                $message = __( 'Post duplicated successfully. You are now editing the duplicate.', 'webtapot-admin-cleaner' );
            } else {
                $message = sprintf( __( '%d posts duplicated successfully.', 'webtapot-admin-cleaner' ), $count );
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }
}

