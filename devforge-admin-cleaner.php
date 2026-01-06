<?php
/**
 * Plugin Name: Admin Toolkit
 * Description: The ultimate WordPress admin customization toolkit.
 * Version: 2.5.0
 * Author: DevForge
 * Author URI: https://profiles.wordpress.org/devforge/
 * License: GPL v2 or later
 * Text Domain: devforge-admin-cleaner
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'WAC_VERSION', '2.5.0' );
define( 'WAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Freemius
if ( ! function_exists( 'wac_fs' ) ) {
    function wac_fs() {
        global $wac_fs;

        if ( ! isset( $wac_fs ) ) {
            $freemius_path = dirname( __FILE__ ) . '/vendor/freemius/start.php';
            
            if ( ! file_exists( $freemius_path ) ) {
                return null;
            }
            
            require_once $freemius_path;

            $wac_fs = fs_dynamic_init( array(
                'id'                  => '22593',
                'slug'                => 'devforge-admin-cleaner',
                'type'                => 'plugin',
                'public_key'          => 'pk_90248dc6b1f90534fbc8021d2d714',
                'is_premium'          => false,
                'premium_suffix'      => 'Pro',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'    => 'devforge-admin-cleaner',
                    'support' => false,
                ),
            ) );
        }

        return $wac_fs;
    }

    wac_fs();
    
    if ( wac_fs() ) {
        do_action( 'wac_fs_loaded' );
    }
}

// Helper functions (define early)
function wac_is_premium() {
    $fs = wac_fs();
    
    // Freemius yoksa kesinlikle false
    if ( ! $fs ) {
        return false;
    }
    
    // Sadece gerçekten aktif ve geçerli lisansı olan kullanıcılar için true
    // has_active_valid_license() en sıkı kontrol
    if ( method_exists( $fs, 'has_active_valid_license' ) ) {
        return $fs->has_active_valid_license();
    }
    
    // Fallback: is_paying() kontrolü (sadece gerçek ödeme yapanlar)
    return $fs->is_paying();
}

function wac_get_option( $key, $default = '' ) {
    $options = get_option( 'wac_settings', array() );
    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

function wac_get_roles() {
    global $wp_roles;
    if ( ! $wp_roles ) return array();
    $roles = array();
    foreach ( $wp_roles->roles as $key => $role ) {
        $roles[ $key ] = $role['name'];
    }
    return $roles;
}

// Load CSS/JS - Register early
add_action( 'admin_enqueue_scripts', 'wac_enqueue_assets' );
function wac_enqueue_assets( $hook ) {
    // Check if we're on our page
    if ( strpos( $hook, 'admin-cleaner' ) !== false || 
         ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'admin-cleaner' ) !== false ) ) {
        
        wp_enqueue_style( 
            'wac-admin', 
            WAC_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            WAC_VERSION 
        );
        
        // Sortable.js for drag & drop
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            array(),
            '1.15.0',
            true
        );
        
        wp_enqueue_script( 
            'wac-admin', 
            WAC_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery', 'sortablejs' ), 
            WAC_VERSION, 
            true 
        );
        
        wp_localize_script( 'wac-admin', 'wacAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wac_admin_nonce' ),
        ) );
    }
}

// Include files
function wac_includes() {
    require_once WAC_PLUGIN_DIR . 'includes/class-settings.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-dashboard-widgets.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-admin-cleaner.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-role-manager.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-login-redirect.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-disable-features.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-notices-cleaner.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-maintenance-mode.php';
    require_once WAC_PLUGIN_DIR . 'includes/class-performance-cleaner.php';
    
    // Heartbeat Control - Available in Free version
    if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-heartbeat-control.php' ) ) {
        require_once WAC_PLUGIN_DIR . 'includes/pro/class-heartbeat-control.php';
    }
    
    // Admin Announcements - Available in Free version
    if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-admin-announcements.php' ) ) {
        require_once WAC_PLUGIN_DIR . 'includes/pro/class-admin-announcements.php';
    }
    
    if ( wac_is_premium() ) {
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-white-label.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-white-label.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-login-customizer.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-login-customizer.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-export-import.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-export-import.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-activity-log.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-activity-log.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-security-tweaks.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-security-tweaks.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-menu-editor.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-menu-editor.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-dashboard-builder.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-dashboard-builder.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-admin-theme.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-admin-theme.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-role-editor.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-role-editor.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-admin-columns.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-admin-columns.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-command-palette.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-command-palette.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-duplicate-post.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-duplicate-post.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-media-cleanup.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-media-cleanup.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-login-history.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-login-history.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-heartbeat-control.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-heartbeat-control.php';
        }
        if ( file_exists( WAC_PLUGIN_DIR . 'includes/pro/class-admin-announcements.php' ) ) {
            require_once WAC_PLUGIN_DIR . 'includes/pro/class-admin-announcements.php';
        }
    }
}

// Load text domain
add_action( 'plugins_loaded', 'wac_load_textdomain' );
function wac_load_textdomain() {
    load_plugin_textdomain( 'devforge-admin-cleaner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Initialize
function wac_init() {
    wac_includes();
    
    WAC_Settings::get_instance();
    WAC_Dashboard_Widgets::get_instance();
    WAC_Admin_Cleaner::get_instance();
        // WAC_Role_Manager::get_instance(); // Disabled - Role Menus feature removed, using Menu Editor instead
        WAC_Login_Redirect::get_instance();
    WAC_Disable_Features::get_instance();
    WAC_Notices_Cleaner::get_instance();
    WAC_Maintenance_Mode::get_instance();
    WAC_Performance_Cleaner::get_instance();
    
    if ( wac_is_premium() ) {
        if ( class_exists( 'WAC_White_Label' ) ) WAC_White_Label::get_instance();
        if ( class_exists( 'WAC_Login_Customizer' ) ) WAC_Login_Customizer::get_instance();
        if ( class_exists( 'WAC_Export_Import' ) ) WAC_Export_Import::get_instance();
        if ( class_exists( 'WAC_Activity_Log' ) ) WAC_Activity_Log::get_instance();
        if ( class_exists( 'WAC_Security_Tweaks' ) ) WAC_Security_Tweaks::get_instance();
        if ( class_exists( 'WAC_Menu_Editor' ) ) WAC_Menu_Editor::get_instance();
        if ( class_exists( 'WAC_Dashboard_Builder' ) ) WAC_Dashboard_Builder::get_instance();
        if ( class_exists( 'WAC_Admin_Theme' ) ) WAC_Admin_Theme::get_instance();
        if ( class_exists( 'WAC_Role_Editor' ) ) WAC_Role_Editor::get_instance();
        if ( class_exists( 'WAC_Admin_Columns' ) ) WAC_Admin_Columns::get_instance();
        if ( class_exists( 'WAC_Command_Palette' ) ) WAC_Command_Palette::get_instance();
        if ( class_exists( 'WAC_Duplicate_Post' ) ) WAC_Duplicate_Post::get_instance();
        if ( class_exists( 'WAC_Media_Cleanup' ) ) WAC_Media_Cleanup::get_instance();
        if ( class_exists( 'WAC_Login_History' ) ) WAC_Login_History::get_instance();
        if ( class_exists( 'WAC_Heartbeat_Control' ) ) WAC_Heartbeat_Control::get_instance();
        if ( class_exists( 'WAC_Admin_Announcements' ) ) WAC_Admin_Announcements::get_instance();
    }
}
add_action( 'plugins_loaded', 'wac_init' );

// Activation
register_activation_hook( __FILE__, function() {
    if ( ! get_option( 'wac_settings' ) ) {
        update_option( 'wac_settings', array(
            'hide_dashboard_widgets' => array(),
            'role_menu_settings'     => array(),
            'hide_admin_bar_items'   => array(),
        ) );
    }
} );
