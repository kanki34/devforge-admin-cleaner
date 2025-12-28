<?php
/**
 * Login Page Customizer (PRO)
 * Customize the WordPress login page appearance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Login_Customizer {

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
        
        add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ) );
        add_filter( 'login_headerurl', array( $this, 'login_logo_url' ) );
        add_filter( 'login_headertext', array( $this, 'login_logo_title' ) );
    }

    /**
     * Enqueue login page styles
     */
    public function login_styles() {
        $logo = ! empty( $this->options['login_logo'] ) ? $this->options['login_logo'] : '';
        $bg_color = ! empty( $this->options['login_bg_color'] ) ? $this->options['login_bg_color'] : '#f1f1f1';
        $form_bg = ! empty( $this->options['login_form_bg'] ) ? $this->options['login_form_bg'] : '#ffffff';
        $btn_color = ! empty( $this->options['login_btn_color'] ) ? $this->options['login_btn_color'] : '#2271b1';
        $custom_css = ! empty( $this->options['login_custom_css'] ) ? $this->options['login_custom_css'] : '';
        ?>
        <style type="text/css">
            /* Background */
            body.login {
                background-color: <?php echo esc_attr( $bg_color ); ?> !important;
            }
            
            /* Logo */
            <?php if ( ! empty( $logo ) ) : ?>
            #login h1 a, .login h1 a {
                background-image: url('<?php echo esc_url( $logo ); ?>') !important;
                background-size: contain !important;
                background-repeat: no-repeat !important;
                background-position: center center !important;
                width: 100% !important;
                height: 100px !important;
                margin-bottom: 20px !important;
            }
            <?php endif; ?>
            
            /* Form */
            .login form {
                background: <?php echo esc_attr( $form_bg ); ?> !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
                border: none !important;
            }
            
            /* Button */
            .wp-core-ui .button-primary {
                background: <?php echo esc_attr( $btn_color ); ?> !important;
                border-color: <?php echo esc_attr( $btn_color ); ?> !important;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
                border-radius: 4px !important;
                text-shadow: none !important;
                padding: 8px 20px !important;
                height: auto !important;
                font-size: 14px !important;
            }
            
            .wp-core-ui .button-primary:hover,
            .wp-core-ui .button-primary:focus {
                background: <?php echo esc_attr( $this->adjust_brightness( $btn_color, -20 ) ); ?> !important;
                border-color: <?php echo esc_attr( $this->adjust_brightness( $btn_color, -20 ) ); ?> !important;
            }
            
            /* Input fields */
            .login form .input,
            .login input[type="text"],
            .login input[type="password"] {
                border-radius: 4px !important;
                border: 1px solid #ddd !important;
                padding: 10px 12px !important;
                font-size: 14px !important;
            }
            
            .login form .input:focus,
            .login input[type="text"]:focus,
            .login input[type="password"]:focus {
                border-color: <?php echo esc_attr( $btn_color ); ?> !important;
                box-shadow: 0 0 0 1px <?php echo esc_attr( $btn_color ); ?> !important;
            }
            
            /* Links */
            .login #nav a,
            .login #backtoblog a {
                color: <?php echo esc_attr( $btn_color ); ?> !important;
            }
            
            /* Messages */
            .login .message,
            .login .success {
                border-left-color: <?php echo esc_attr( $btn_color ); ?> !important;
                border-radius: 4px !important;
            }
            
            /* Remember me checkbox */
            .login form .forgetmenot label {
                font-size: 13px !important;
            }
            
            /* Custom CSS */
            <?php echo wp_strip_all_tags( $custom_css ); ?>
        </style>
        <?php
    }

    /**
     * Change login logo URL
     */
    public function login_logo_url() {
        return home_url();
    }

    /**
     * Change login logo title
     */
    public function login_logo_title() {
        return get_bloginfo( 'name' );
    }

    /**
     * Adjust color brightness
     */
    private function adjust_brightness( $hex, $steps ) {
        $hex = ltrim( $hex, '#' );
        
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );
        
        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }
}

