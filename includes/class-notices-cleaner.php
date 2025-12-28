<?php
/**
 * Admin Notices Cleaner
 * Hide annoying admin notices and nags
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Notices_Cleaner {

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
        
        // Hide all notices
        if ( ! empty( $this->options['hide_all_notices'] ) ) {
            $this->hide_all_notices();
        }
        
        // Hide update notices
        if ( ! empty( $this->options['hide_update_notices'] ) ) {
            $this->hide_update_notices();
        }
    }

    /**
     * Hide all admin notices
     */
    private function hide_all_notices() {
        add_action( 'admin_head', function() {
            // Only for non-administrators
            if ( current_user_can( 'administrator' ) ) {
                return;
            }
            
            echo '<style>
                .notice, 
                .notice-error, 
                .notice-warning, 
                .notice-success, 
                .notice-info,
                .update-nag,
                .updated,
                .error,
                .is-dismissible,
                #wpbody-content > .notice,
                #wpbody-content > .error,
                #wpbody-content > .updated {
                    display: none !important;
                }
            </style>';
        });
        
        // Remove all notices via action
        add_action( 'admin_notices', function() {
            if ( ! current_user_can( 'administrator' ) ) {
                remove_all_actions( 'admin_notices' );
            }
        }, 0 );
    }

    /**
     * Hide update notices
     */
    private function hide_update_notices() {
        // Hide core update nag
        add_action( 'admin_head', function() {
            if ( ! current_user_can( 'administrator' ) ) {
                remove_action( 'admin_notices', 'update_nag', 3 );
                remove_action( 'admin_notices', 'maintenance_nag', 10 );
            }
        }, 1 );
        
        // Hide update row in plugins list
        add_action( 'admin_head', function() {
            if ( ! current_user_can( 'update_plugins' ) ) {
                echo '<style>.plugin-update-tr, .update-message { display: none !important; }</style>';
            }
        });
        
        // Hide theme updates
        add_action( 'admin_head', function() {
            if ( ! current_user_can( 'update_themes' ) ) {
                echo '<style>.theme-update-message { display: none !important; }</style>';
            }
        });
    }

    /**
     * Get notice options
     */
    public static function get_notice_options() {
        return array(
            'hide_all_notices' => array(
                'label' => __( 'Hide All Notices', 'webtapot-admin-cleaner' ),
                'description' => __( 'Hide all admin notices for non-admin users', 'webtapot-admin-cleaner' ),
            ),
            'hide_update_notices' => array(
                'label' => __( 'Hide Update Notices', 'webtapot-admin-cleaner' ),
                'description' => __( 'Hide plugin/theme update notifications', 'webtapot-admin-cleaner' ),
            ),
        );
    }
}

