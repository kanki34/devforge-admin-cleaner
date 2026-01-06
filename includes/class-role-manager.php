<?php
/**
 * Role-based Menu Manager
 * Control which menus each role can see
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Role_Manager {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'apply_role_menu_restrictions' ), 9999 );
    }

    /**
     * Apply menu restrictions based on user role
     */
    public function apply_role_menu_restrictions() {
        // Don't affect administrators
        if ( current_user_can( 'administrator' ) ) {
            return;
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        if ( empty( $user_roles ) ) {
            return;
        }

        $current_role = $user_roles[0]; // Primary role
        $options = get_option( 'wac_settings', array() );
        $role_settings = isset( $options['role_menu_settings'] ) ? $options['role_menu_settings'] : array();
        
        if ( empty( $role_settings ) || ! isset( $role_settings[ $current_role ] ) ) {
            return;
        }

        $hidden_menus = $role_settings[ $current_role ];
        
        if ( empty( $hidden_menus ) || ! is_array( $hidden_menus ) ) {
            return;
        }

        foreach ( $hidden_menus as $menu_slug ) {
            remove_menu_page( $menu_slug );
        }
    }

    /**
     * Get all admin menu items
     */
    public static function get_admin_menu_items() {
        global $menu;
        
        $items = array();
        
        if ( empty( $menu ) ) {
            return self::get_default_menu_items();
        }

        foreach ( $menu as $item ) {
            if ( ! empty( $item[0] ) && ! empty( $item[2] ) ) {
                // Clean menu title (remove notification bubbles)
                $title = preg_replace( '/<span.*<\/span>/i', '', $item[0] );
                $title = trim( wp_strip_all_tags( $title ) );
                
                if ( ! empty( $title ) && $item[2] !== 'admin-toolkit' ) {
                    $items[ $item[2] ] = $title;
                }
            }
        }

        return $items;
    }

    /**
     * Get default menu items (fallback)
     */
    public static function get_default_menu_items() {
        return array(
            'index.php'              => __( 'Dashboard', 'admin-toolkit' ),
            'edit.php'               => __( 'Posts', 'admin-toolkit' ),
            'upload.php'             => __( 'Media', 'admin-toolkit' ),
            'edit.php?post_type=page'=> __( 'Pages', 'admin-toolkit' ),
            'edit-comments.php'      => __( 'Comments', 'admin-toolkit' ),
            'themes.php'             => __( 'Appearance', 'admin-toolkit' ),
            'plugins.php'            => __( 'Plugins', 'admin-toolkit' ),
            'users.php'              => __( 'Users', 'admin-toolkit' ),
            'tools.php'              => __( 'Tools', 'admin-toolkit' ),
            'options-general.php'    => __( 'Settings', 'admin-toolkit' ),
        );
    }
}

