<?php
/**
 * White Label Mode (PRO)
 * Remove WordPress branding and add custom branding
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_White_Label {

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
        
        if ( empty( $this->options['white_label_enabled'] ) ) {
            return;
        }
        
        // Custom admin logo
        if ( ! empty( $this->options['custom_admin_logo'] ) ) {
            add_action( 'admin_head', array( $this, 'custom_admin_logo' ) );
        }
        
        // Custom footer text
        if ( ! empty( $this->options['custom_footer_text'] ) ) {
            add_filter( 'admin_footer_text', array( $this, 'custom_footer_text' ) );
            add_filter( 'update_footer', '__return_empty_string', 11 );
        }
        
        // Hide WP logo from admin bar
        if ( ! empty( $this->options['hide_wp_logo'] ) ) {
            add_action( 'wp_before_admin_bar_render', array( $this, 'hide_wp_logo' ) );
        }
        
        // Custom admin CSS
        if ( ! empty( $this->options['custom_admin_css'] ) ) {
            add_action( 'admin_head', array( $this, 'custom_admin_css' ) );
        }
    }

    /**
     * Add custom admin logo
     */
    public function custom_admin_logo() {
        $logo_url = $this->options['custom_admin_logo'];
        ?>
        <style>
            #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
                content: '' !important;
                background: url('<?php echo esc_url( $logo_url ); ?>') no-repeat center center !important;
                background-size: contain !important;
                width: 20px !important;
                height: 20px !important;
            }
            #adminmenu .wp-menu-image.dashicons-before.dashicons-admin-home:before {
                content: '' !important;
                background: url('<?php echo esc_url( $logo_url ); ?>') no-repeat center center !important;
                background-size: 20px 20px !important;
            }
        </style>
        <?php
    }

    /**
     * Custom footer text
     */
    public function custom_footer_text( $text ) {
        return wp_kses_post( $this->options['custom_footer_text'] );
    }

    /**
     * Hide WordPress logo from admin bar
     */
    public function hide_wp_logo() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu( 'wp-logo' );
    }

    /**
     * Add custom admin CSS
     */
    public function custom_admin_css() {
        if ( ! empty( $this->options['custom_admin_css'] ) ) {
            echo '<style>' . wp_strip_all_tags( $this->options['custom_admin_css'] ) . '</style>';
        }
    }
}

