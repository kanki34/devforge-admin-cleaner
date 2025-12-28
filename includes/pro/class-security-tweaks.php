<?php
/**
 * Security Tweaks (PRO)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Security_Tweaks {

    private static $instance = null;
    private $options;
    private $custom_slug;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'wac_settings', array() );
        $this->custom_slug = ! empty( $this->options['custom_login_url'] ) ? sanitize_title( $this->options['custom_login_url'] ) : '';
        
        // Disable file editor
        if ( ! empty( $this->options['disable_file_editor'] ) ) {
            if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
                define( 'DISALLOW_FILE_EDIT', true );
            }
        }
        
        // Hide login errors
        if ( ! empty( $this->options['hide_login_errors'] ) ) {
            add_filter( 'login_errors', array( $this, 'hide_login_errors' ) );
        }
        
        // Disable author archives
        if ( ! empty( $this->options['disable_author_archives'] ) ) {
            add_action( 'template_redirect', array( $this, 'disable_author_archives' ) );
        }
        
        // Custom login URL
        if ( $this->custom_slug ) {
            add_action( 'init', array( $this, 'handle_custom_login' ), 1 );
            add_action( 'wp_loaded', array( $this, 'block_default_login' ) );
            add_action( 'wp_loaded', array( $this, 'block_wp_admin' ) );
            // Don't filter login_url to avoid exposing custom URL in redirects
        }
        
        // Limit login attempts
        if ( ! empty( $this->options['limit_login_attempts'] ) ) {
            add_filter( 'authenticate', array( $this, 'check_login_attempts' ), 30, 3 );
            add_action( 'wp_login_failed', array( $this, 'log_failed_attempt' ) );
            add_action( 'wp_login', array( $this, 'clear_login_attempts' ), 10, 2 );
        }
        
        // Force strong passwords
        if ( ! empty( $this->options['force_strong_passwords'] ) ) {
            add_action( 'user_profile_update_errors', array( $this, 'check_password_strength' ), 10, 3 );
        }
    }

    /**
     * Hide login errors
     */
    public function hide_login_errors( $error ) {
        return __( 'Invalid credentials.', 'webtapot-admin-cleaner' );
    }

    /**
     * Disable author archives
     */
    public function disable_author_archives() {
        if ( is_author() ) {
            wp_redirect( home_url(), 301 );
            exit;
        }
    }

    /**
     * Handle custom login URL - show login form at custom URL
     */
    public function handle_custom_login() {
        $request = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
        $home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
        
        if ( $home_path ) {
            $request = preg_replace( '#^' . preg_quote( $home_path, '#' ) . '/#', '', $request );
        }
        
        // If requesting custom login URL, show login
        if ( $request === $this->custom_slug ) {
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }

    /**
     * Block default wp-login.php access
     */
    public function block_default_login() {
        // Don't block if already logged in admin
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $request = $_SERVER['REQUEST_URI'];
        
        // Check if trying to access wp-login.php
        if ( strpos( $request, 'wp-login.php' ) !== false ) {
            $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
            
            // Allow these actions (logout, password reset, etc.)
            $allowed = array( 'logout', 'postpass', 'rp', 'resetpass', 'lostpassword', 'confirmaction' );
            
            if ( ! in_array( $action, $allowed ) && ! isset( $_GET['key'] ) ) {
                // Show 404
                status_header( 404 );
                nocache_headers();
                include( get_query_template( '404' ) );
                exit;
            }
        }
    }

    /**
     * Block wp-admin access for non-logged users
     */
    public function block_wp_admin() {
        // Only block if not logged in
        if ( is_user_logged_in() ) {
            return;
        }
        
        // Check if trying to access wp-admin
        $request = $_SERVER['REQUEST_URI'];
        
        if ( strpos( $request, '/wp-admin' ) !== false && strpos( $request, '/wp-admin/admin-ajax.php' ) === false ) {
            // Show 404 instead of redirecting to login
            status_header( 404 );
            nocache_headers();
            
            // Try to load theme's 404
            $template = get_query_template( '404' );
            if ( $template ) {
                include( $template );
            } else {
                echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 - Not Found</h1></body></html>';
            }
            exit;
        }
    }

    /**
     * Check login attempts
     */
    public function check_login_attempts( $user, $username, $password ) {
        if ( empty( $username ) ) {
            return $user;
        }

        $ip = $this->get_ip();
        $attempts = get_transient( 'wac_login_attempts_' . md5( $ip ) );
        $max = isset( $this->options['max_login_attempts'] ) ? intval( $this->options['max_login_attempts'] ) : 5;
        $lockout = isset( $this->options['login_lockout_time'] ) ? intval( $this->options['login_lockout_time'] ) : 15;

        if ( $attempts >= $max ) {
            return new WP_Error(
                'too_many_attempts',
                sprintf( __( 'Too many failed attempts. Try again in %d minutes.', 'webtapot-admin-cleaner' ), $lockout )
            );
        }

        return $user;
    }

    /**
     * Log failed attempt
     */
    public function log_failed_attempt( $username ) {
        $ip = $this->get_ip();
        $key = 'wac_login_attempts_' . md5( $ip );
        $attempts = get_transient( $key );
        $attempts = $attempts ? intval( $attempts ) + 1 : 1;
        $lockout = isset( $this->options['login_lockout_time'] ) ? intval( $this->options['login_lockout_time'] ) : 15;
        
        set_transient( $key, $attempts, $lockout * MINUTE_IN_SECONDS );
    }

    /**
     * Clear attempts on success
     */
    public function clear_login_attempts( $user_login, $user ) {
        $ip = $this->get_ip();
        delete_transient( 'wac_login_attempts_' . md5( $ip ) );
    }

    /**
     * Check password strength
     */
    public function check_password_strength( $errors, $update, $user ) {
        if ( ! empty( $_POST['pass1'] ) ) {
            $pass = $_POST['pass1'];
            
            if ( strlen( $pass ) < 8 ) {
                $errors->add( 'weak_password', __( 'Password must be at least 8 characters.', 'webtapot-admin-cleaner' ) );
            }
            if ( ! preg_match( '/[A-Z]/', $pass ) ) {
                $errors->add( 'weak_password', __( 'Password must contain an uppercase letter.', 'webtapot-admin-cleaner' ) );
            }
            if ( ! preg_match( '/[0-9]/', $pass ) ) {
                $errors->add( 'weak_password', __( 'Password must contain a number.', 'webtapot-admin-cleaner' ) );
            }
        }
    }

    /**
     * Get IP
     */
    private function get_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        }
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    }

    /**
     * Get options for settings page
     */
    public static function get_options() {
        return array(
            'disable_file_editor' => array(
                'label' => __( 'Disable File Editor', 'webtapot-admin-cleaner' ),
                'description' => __( 'Remove Theme/Plugin editor', 'webtapot-admin-cleaner' ),
            ),
            'hide_login_errors' => array(
                'label' => __( 'Hide Login Errors', 'webtapot-admin-cleaner' ),
                'description' => __( 'Generic error on failed login', 'webtapot-admin-cleaner' ),
            ),
            'disable_author_archives' => array(
                'label' => __( 'Disable Author Archives', 'webtapot-admin-cleaner' ),
                'description' => __( 'Redirect author pages to home', 'webtapot-admin-cleaner' ),
            ),
            'limit_login_attempts' => array(
                'label' => __( 'Limit Login Attempts', 'webtapot-admin-cleaner' ),
                'description' => __( 'Block after failed attempts', 'webtapot-admin-cleaner' ),
            ),
            'force_strong_passwords' => array(
                'label' => __( 'Force Strong Passwords', 'webtapot-admin-cleaner' ),
                'description' => __( 'Require strong passwords', 'webtapot-admin-cleaner' ),
            ),
        );
    }
}
