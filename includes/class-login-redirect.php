<?php
/**
 * Login Redirect Manager
 * Redirect users to different pages based on their role
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Login_Redirect {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'login_redirect', array( $this, 'role_based_redirect' ), 10, 3 );
    }

    /**
     * Redirect users based on their role after login
     */
    public function role_based_redirect( $redirect_to, $request, $user ) {
        if ( ! isset( $user->roles ) || ! is_array( $user->roles ) ) {
            return $redirect_to;
        }

        $options = get_option( 'wac_settings', array() );
        $redirects = isset( $options['login_redirect'] ) ? $options['login_redirect'] : array();
        
        if ( empty( $redirects ) ) {
            return $redirect_to;
        }

        // Check each role (primary role first)
        foreach ( $user->roles as $role ) {
            if ( isset( $redirects[ $role ] ) && ! empty( $redirects[ $role ] ) ) {
                return esc_url( $redirects[ $role ] );
            }
        }

        return $redirect_to;
    }

    /**
     * Get common redirect options
     */
    public static function get_redirect_options() {
        return array(
            ''                      => __( 'Default (Dashboard)', 'devforge-admin-cleaner' ),
            home_url()              => __( 'Homepage', 'devforge-admin-cleaner' ),
            admin_url()             => __( 'Admin Dashboard', 'devforge-admin-cleaner' ),
            admin_url( 'profile.php' ) => __( 'Profile Page', 'devforge-admin-cleaner' ),
            admin_url( 'edit.php' ) => __( 'Posts List', 'devforge-admin-cleaner' ),
            'custom'                => __( 'Custom URL', 'devforge-admin-cleaner' ),
        );
    }
}

