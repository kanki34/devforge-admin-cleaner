<?php
/**
 * Maintenance Mode
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Maintenance_Mode {

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
        
        if ( ! empty( $this->options['maintenance_enabled'] ) ) {
            add_action( 'template_redirect', array( $this, 'show_maintenance_page' ) );
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_notice' ), 100 );
        }
    }

    public function show_maintenance_page() {
        // CRITICAL: Never show maintenance page on admin pages
        if ( is_admin() ) {
            return;
        }
        
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            return;
        }
        
        if ( $this->is_login_page() ) {
            return;
        }

        $title = $this->options['maintenance_title'] ?? __( 'We\'ll be right back', 'admin-toolkit' );
        $message = $this->options['maintenance_message'] ?? __( 'Our site is currently undergoing scheduled maintenance. Please check back shortly.', 'admin-toolkit' );
        $bg_color = $this->options['maintenance_bg_color'] ?? '#ffffff';
        $text_color = $this->options['maintenance_text_color'] ?? '#1d1d1f';
        $bg_image = $this->options['maintenance_bg_image'] ?? '';

        status_header( 503 );
        header( 'Retry-After: 3600' );
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html( $title ); ?> - <?php bloginfo( 'name' ); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: <?php echo esc_attr( $bg_color ); ?>;
                    color: <?php echo esc_attr( $text_color ); ?>;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 40px 20px;
                    <?php if ( $bg_image ) : ?>
                    background-image: url('<?php echo esc_url( $bg_image ); ?>');
                    background-size: cover;
                    background-position: center;
                    <?php endif; ?>
                }
                .container {
                    max-width: 480px;
                    text-align: center;
                }
                h1 {
                    font-size: 32px;
                    font-weight: 600;
                    margin-bottom: 16px;
                    line-height: 1.3;
                }
                p {
                    font-size: 16px;
                    line-height: 1.6;
                    opacity: 0.7;
                }
                .status {
                    display: inline-block;
                    padding: 6px 12px;
                    background: rgba(0,0,0,0.05);
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 24px;
                    opacity: 0.6;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <?php if ( wac_is_premium() ) : ?>
                <div class="status">Maintenance</div>
                <?php endif; ?>
                <h1><?php echo esc_html( $title ); ?></h1>
                <p><?php echo esc_html( $message ); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    private function is_login_page() {
        return in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php' ) );
    }

    public function admin_bar_notice( $wp_admin_bar ) {
        $wp_admin_bar->add_node( array(
            'id'    => 'wac-maintenance-notice',
            'title' => '<span style="background:#ff9500;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Maintenance Mode</span>',
            'href'  => admin_url( 'admin.php?page=admin-toolkit&tab=features' ),
        ) );
    }
}
