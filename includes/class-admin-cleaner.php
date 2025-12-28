<?php
/**
 * Admin Cleaner Class
 * Handles admin bar and general cleanup tasks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Admin_Cleaner {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin bar cleanup
        add_action( 'wp_before_admin_bar_render', array( $this, 'hide_admin_bar_items' ), 999 );
        
        // Screen options and help tab
        add_action( 'admin_head', array( $this, 'hide_screen_options' ) );
        add_action( 'admin_head', array( $this, 'hide_help_tab' ) );
    }

    /**
     * Hide admin bar items
     */
    public function hide_admin_bar_items() {
        global $wp_admin_bar;
        
        $options = get_option( 'wac_settings', array() );
        $hidden_bar = isset( $options['hide_admin_bar_items'] ) ? $options['hide_admin_bar_items'] : array();
        
        if ( empty( $hidden_bar ) || ! is_array( $hidden_bar ) ) {
            return;
        }

        foreach ( $hidden_bar as $bar_id ) {
            $wp_admin_bar->remove_node( $bar_id );
        }
    }

    /**
     * Hide screen options
     */
    public function hide_screen_options() {
        $options = get_option( 'wac_settings', array() );
        
        if ( empty( $options['hide_screen_options'] ) ) {
            return;
        }

        echo '<style>#screen-options-link-wrap { display: none !important; }</style>';
    }

    /**
     * Hide help tab
     */
    public function hide_help_tab() {
        $options = get_option( 'wac_settings', array() );
        
        if ( empty( $options['hide_help_tab'] ) ) {
            return;
        }

        echo '<style>#contextual-help-link-wrap { display: none !important; }</style>';
    }
}
