<?php
/**
 * Settings Page - Enhanced Version
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Settings {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_reset' ) );
    }

    public function add_menu() {
        $hook = add_menu_page(
            'Admin Cleaner',
            'Admin Cleaner',
            'manage_options',
            'webtapot-admin-cleaner',
            array( $this, 'render_page' ),
            'dashicons-admin-tools',
            80
        );
        
        // Load media uploader on our page
        add_action( 'load-' . $hook, function() {
            wp_enqueue_media();
        });
    }

    public function register_settings() {
        register_setting( 'wac_settings_group', 'wac_settings', array( $this, 'sanitize' ) );
        
        // Add success message after settings save
        add_action( 'admin_notices', array( $this, 'settings_saved_notice' ) );
    }
    
    /**
     * Show success notice after settings save
     */
    public function settings_saved_notice() {
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'webtapot-admin-cleaner' ) {
                // Use JavaScript notification instead of WordPress notice
                echo '<script>jQuery(function($){if(typeof wacShowNotification !== "undefined"){wacShowNotification("Settings saved successfully!","success");}});</script>';
            }
        }
    }

    public function handle_reset() {
        if ( isset( $_POST['wac_reset'] ) && check_admin_referer( 'wac_reset', '_wac_reset' ) ) {
            delete_option( 'wac_settings' );
            add_settings_error( 'wac_settings', 'reset', 'Settings reset.', 'updated' );
        }
    }

    public function sanitize( $input ) {
        $clean = array();
        
        // Arrays
        $arrays = array( 'hide_dashboard_widgets', 'hide_admin_bar_items' );
        foreach ( $arrays as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? array_map( 'sanitize_text_field', $input[ $key ] ) : array();
        }
        
        // Role menu
        $clean['role_menu_settings'] = array();
        if ( isset( $input['role_menu_settings'] ) && is_array( $input['role_menu_settings'] ) ) {
            foreach ( $input['role_menu_settings'] as $role => $menus ) {
                $clean['role_menu_settings'][ sanitize_key( $role ) ] = is_array( $menus ) ? array_map( 'sanitize_text_field', $menus ) : array();
            }
        }
        
        // Login redirect
        $clean['login_redirect'] = array();
        if ( isset( $input['login_redirect'] ) && is_array( $input['login_redirect'] ) ) {
            foreach ( $input['login_redirect'] as $role => $url ) {
                $clean['login_redirect'][ sanitize_key( $role ) ] = esc_url_raw( $url );
            }
        }
        
        // Checkboxes
        $checkboxes = array(
            'hide_screen_options', 'hide_help_tab', 'hide_all_notices', 'hide_update_notices',
            'disable_comments', 'disable_emojis', 'disable_rss', 'disable_xmlrpc',
            'disable_rest_api', 'disable_gutenberg', 'remove_wp_version',
            'maintenance_enabled', 'white_label_enabled', 'hide_wp_logo',
            'disable_file_editor', 'hide_login_errors', 'disable_author_archives',
            'limit_login_attempts', 'force_strong_passwords',
            'command_palette_enabled', 'command_palette_show_admin_bar_icon',
            'duplicate_enabled', 'duplicate_admin_bar', 'duplicate_bulk',
        );
        foreach ( $checkboxes as $key ) {
            $clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
        }
        
        // Text fields
        $texts = array( 
            'maintenance_title', 'maintenance_message', 'maintenance_icon',
            'custom_footer_text', 'custom_login_url', 'admin_color_scheme',
            'login_btn_text'
        );
        foreach ( $texts as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
        }
        
        // URLs
        $urls = array( 'custom_admin_logo', 'login_logo', 'maintenance_bg_image', 'login_bg_image' );
        foreach ( $urls as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( $input[ $key ] ) : '';
        }
        
        // Colors
        $colors = array( 
            'maintenance_bg_color', 'maintenance_text_color', 'maintenance_btn_color',
            'login_bg_color', 'login_form_bg', 'login_btn_color', 'login_text_color',
            'admin_accent_color'
        );
        foreach ( $colors as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
        }
        
        // Numbers
        $clean['max_login_attempts'] = isset( $input['max_login_attempts'] ) ? absint( $input['max_login_attempts'] ) : 5;
        $clean['login_lockout_time'] = isset( $input['login_lockout_time'] ) ? absint( $input['login_lockout_time'] ) : 15;
        $clean['maintenance_blur'] = isset( $input['maintenance_blur'] ) ? absint( $input['maintenance_blur'] ) : 0;
        
        // CSS
        $clean['login_custom_css'] = isset( $input['login_custom_css'] ) ? wp_strip_all_tags( $input['login_custom_css'] ) : '';
        $clean['custom_admin_css'] = isset( $input['custom_admin_css'] ) ? wp_strip_all_tags( $input['custom_admin_css'] ) : '';
        
        return $clean;
    }

    public function render_page() {
        $opt = get_option( 'wac_settings', array() );
        if ( ! is_array( $opt ) ) $opt = array();
        
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        $is_pro = wac_is_premium();
        
        // Inline CSS
        $this->render_styles();
        ?>
        <div class="wrap wac-settings-wrap">
            <div style="margin-bottom:32px">
                <h1>
                    Admin Cleaner
                    <span class="wac-version">v<?php echo WAC_VERSION; ?></span>
                    <?php if ( $is_pro ) : ?><span class="wac-pro-badge">PRO</span><?php endif; ?>
                </h1>
                <p style="margin:8px 0 0;font-size:14px;color:#86868b;max-width:600px">
                    Customize your WordPress admin area, improve productivity, and enhance security with powerful tools.
                </p>
            </div>

            <?php settings_errors( 'wac_settings' ); ?>

            <nav class="wac-tabs">
                <?php
                $tabs = array(
                    'dashboard'    => 'Dashboard',
                    'appearance'   => 'Appearance',
                    'menus-roles'  => 'Menus & Roles',
                    'productivity' => 'Productivity',
                    'cleanup'      => 'Cleanup',
                    'disable-features' => 'Disable Features',
                    'security'     => 'Security',
                    'tools'        => 'Tools',
                );
                $pro_tabs = array( 
                    'appearance', 'menus-roles', 'productivity', 'security', 'tools'
                );
                foreach ( $tabs as $id => $name ) :
                    $url = admin_url( 'admin.php?page=webtapot-admin-cleaner&tab=' . $id );
                    $class = ( $tab === $id ) ? 'nav-tab nav-tab-active' : 'nav-tab';
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $class; ?>">
                        <?php echo esc_html( $name ); ?>
                        <?php if ( in_array( $id, $pro_tabs ) && ! $is_pro ) : ?>
                            <span class="wac-pro-badge-small">PRO</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php" class="wac-settings-form" id="wac-settings-form">
                <?php settings_fields( 'wac_settings_group' ); ?>
                
                <div id="wac-notification" class="wac-notification" style="display:none"></div>
                
                <div class="wac-tab-content">
                    <?php
                    switch ( $tab ) {
                        case 'dashboard':
                            $this->tab_dashboard( $opt, $is_pro );
                            break;
                        case 'appearance':
                            $this->tab_appearance( $opt, $is_pro );
                            break;
                        case 'menus-roles':
                            $this->tab_menus_roles( $opt, $is_pro );
                            break;
                        case 'productivity':
                            $this->tab_productivity( $opt, $is_pro );
                            break;
                        case 'cleanup':
                            $this->tab_cleanup( $opt, $is_pro );
                            break;
                        case 'disable-features':
                            $this->tab_disable_features( $opt, $is_pro );
                            break;
                        case 'security':
                            $this->tab_security( $opt, $is_pro );
                            break;
                        case 'tools':
                            $this->tab_tools( $opt, $is_pro );
                            break;
                        default:
                            $this->tab_dashboard( $opt, $is_pro );
                            break;
                    }
                    ?>
                </div>

                <?php if ( ! in_array( $tab, array( 'tools', 'menus-roles', 'productivity' ) ) ) : ?>
                <div class="submit">
                    <?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php $this->render_scripts(); ?>
        <?php
    }

    private function render_styles() {
        ?>
        <style>
        body.wp-admin #wpcontent{background:#fff}
        .wac-settings-wrap{max-width:900px;margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;color:#1d1d1f;background:#fff;padding:32px 20px 20px}
        .wac-settings-wrap *{box-sizing:border-box}
        .wac-settings-wrap h1{font-size:28px;font-weight:600;margin:0 0 8px;display:flex;align-items:center;gap:12px;color:#1d1d1f;letter-spacing:-.5px}
        .wac-settings-wrap h1 .dashicons{display:none}
        .wac-version{font-size:12px;font-weight:400;color:#86868b;margin-left:4px}
        .wac-pro-badge{font-size:10px;font-weight:600;background:#1d1d1f;color:#fff;padding:4px 10px;border-radius:6px;letter-spacing:.3px}
        .wac-pro-badge-small{font-size:9px;font-weight:600;background:#1d1d1f;color:#fff;padding:2px 6px;border-radius:4px;margin-left:6px;letter-spacing:.2px}
        .wac-tabs{display:flex;background:#f5f5f7;border-radius:8px;padding:4px;margin-bottom:24px;gap:2px;border:1px solid #e5e5ea;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none}
        .wac-tabs::-webkit-scrollbar{display:none}
        .wac-tabs .nav-tab{background:transparent;border:none;padding:7px 14px;font-size:12px;font-weight:500;color:#86868b;border-radius:6px;margin:0;cursor:pointer;outline:none;box-shadow:none;transition:all .15s ease;position:relative;white-space:nowrap;flex-shrink:0;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .wac-tabs .nav-tab:focus{outline:none;box-shadow:none}
        .wac-tabs .nav-tab:hover{background:rgba(0,0,0,.04);color:#1d1d1f}
        .wac-tabs .nav-tab-active,.wac-tabs .nav-tab-active:hover{background:#fff;color:#1d1d1f;box-shadow:0 1px 2px rgba(0,0,0,.06);font-weight:600}
        .wac-tabs .nav-tab .wac-pro-badge-small{margin-left:3px;font-size:8px;padding:1px 4px;line-height:1.2}
        .wac-tab-content{background:#fff;border:1px solid #e5e5ea;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}
        
        /* Fixed Checkbox */
        .wac-checkbox-list{display:flex;flex-direction:column;gap:0}
        .wac-checkbox-item{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #f5f5f7;cursor:pointer;transition:all .15s ease}
        .wac-checkbox-item:last-child{border-bottom:none}
        .wac-checkbox-item:hover{background:#fafafa;margin:0 -28px;padding:14px 28px;border-radius:8px}
        .wac-checkbox-item span{flex:1;font-size:14px;margin-right:16px;font-weight:400;color:#1d1d1f}
        .wac-checkbox-item input[type=checkbox]{-webkit-appearance:none;appearance:none;width:22px;height:22px;border:2px solid #d1d1d6;border-radius:6px;cursor:pointer;position:relative;flex-shrink:0}
        .wac-checkbox-item input[type=checkbox]:checked{background:#007aff;border-color:#007aff}
        .wac-checkbox-item input[type=checkbox]:checked::after{content:'';position:absolute;width:6px;height:10px;border:solid #fff;border-width:0 2px 2px 0;top:4px;left:7px;transform:rotate(45deg)}
        
        .wac-role-block{margin-bottom:24px;padding:16px;background:#f9f9fb;border-radius:8px}
        .wac-role-block:last-child{margin-bottom:0}
        .wac-role-header{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#86868b;margin-bottom:12px}
        .wac-role-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
        .wac-role-grid label{display:flex;align-items:center;gap:8px;font-size:13px;padding:10px 12px;background:#fff;border:1px solid #e5e5ea;border-radius:8px;cursor:pointer;transition:all .15s ease}
        .wac-role-grid label:hover{background:#f5f5f7;border-color:#d1d1d6;transform:translateY(-1px);box-shadow:0 2px 4px rgba(0,0,0,.05)}
        .wac-role-grid input{width:18px;height:18px;accent-color:#007aff;cursor:pointer}
        .wac-row{display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid #f5f5f7;transition:background .15s ease}
        .wac-row:last-child{border-bottom:none}
        .wac-row:hover{background:#fafafa;margin:0 -28px;padding:16px 28px;border-radius:8px}
        .wac-row-label{font-size:14px;color:#1d1d1f;font-weight:400;flex:1}
        .wac-row-label small{display:block;font-size:12px;color:#86868b;margin-top:4px;font-weight:400;line-height:1.4}
        .wac-switch{position:relative;width:51px;height:31px;flex-shrink:0}
        .wac-switch input{opacity:0;width:0;height:0}
        .wac-switch-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#e9e9eb;border-radius:31px;transition:.2s}
        .wac-switch-slider:before{position:absolute;content:"";height:27px;width:27px;left:2px;bottom:2px;background:#fff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.2s}
        .wac-switch input:checked+.wac-switch-slider{background:#34c759}
        .wac-switch input:checked+.wac-switch-slider:before{transform:translateX(20px)}
        
        .wac-settings-section .form-table{margin:0;width:100%}
        .wac-settings-section .form-table th{width:160px;padding:14px 16px 14px 0;font-size:14px;font-weight:500;vertical-align:top;color:#1d1d1f}
        .wac-settings-section .form-table td{padding:14px 0}
        .wac-settings-section .form-table tr{border-bottom:1px solid #f5f5f7}
        .wac-settings-section .form-table tr:last-child{border-bottom:none}
        .wac-settings-section input[type=text],.wac-settings-section input[type=url],.wac-settings-section input[type=number],.wac-settings-section select,.wac-settings-section textarea{font-family:inherit;font-size:14px;padding:8px 12px;border:1px solid #d1d1d6;border-radius:6px;background:#fff}
        .wac-settings-section input:focus,.wac-settings-section textarea:focus,.wac-settings-section select:focus{outline:none;border-color:#007aff;box-shadow:0 0 0 3px rgba(0,122,255,.15)}
        .wac-settings-section input[type=color]{width:50px;height:40px;padding:2px;border-radius:6px;border:1px solid #d1d1d6;cursor:pointer}
        .wac-settings-form .submit{padding:16px 20px;margin:0;background:#f5f5f7;border-top:1px solid #e5e5ea;border-radius:0}
        .wac-settings-form .button-primary,.wac-settings-wrap .button-primary{background:#fff;border:1px solid #d1d1d6;border-radius:6px;padding:8px 20px;font-size:13px;font-weight:500;height:auto;line-height:1.4;box-shadow:none;color:#1d1d1f}
        .wac-settings-form .button-primary:hover,.wac-settings-wrap .button-primary:hover,.wac-settings-form .button-primary:active,.wac-settings-wrap .button-primary:active,.wac-settings-form .button-primary:focus,.wac-settings-wrap .button-primary:focus{background:#1d1d1f;color:#fff;border-color:#1d1d1f;box-shadow:none;outline:none}
        
        /* Notification System */
        .wac-notification{position:fixed;top:32px;right:20px;z-index:100000;max-width:400px;padding:16px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation:wac-slide-in .3s ease-out;font-size:13px;line-height:1.5}
        .wac-notification.success{background:#34c759;color:#fff}
        .wac-notification.error{background:#ff3b30;color:#fff}
        .wac-notification.info{background:#007aff;color:#fff}
        .wac-notification.warning{background:#ff9500;color:#fff}
        @keyframes wac-slide-in{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
        .wac-notification-close{float:right;margin-left:12px;cursor:pointer;opacity:.8;font-size:18px;line-height:1}
        .wac-notification-close:hover{opacity:1}
        .wac-settings-wrap .button-secondary,.wac-settings-wrap .button{background:#e5e5ea;border:none;border-radius:6px;padding:8px 16px;font-size:13px;color:#1d1d1f}
        .wac-settings-wrap .button-secondary:hover,.wac-settings-wrap .button:hover{background:#d1d1d6}
        
        /* Media Upload */
        .wac-media-field{display:flex;gap:8px;align-items:flex-start}
        .wac-media-field input[type=url]{flex:1}
        .wac-media-btn{background:#007aff!important;color:#fff!important;border:none!important;padding:8px 14px!important;border-radius:6px!important;cursor:pointer;font-size:12px!important}
        .wac-media-btn:hover{background:#0056b3!important}
        .wac-media-preview{margin-top:8px}
        .wac-media-preview img{max-width:200px;max-height:100px;border-radius:6px;border:1px solid #d1d1d6}
        
        /* Color Row */
        .wac-color-row{display:flex;gap:16px;flex-wrap:wrap}
        .wac-color-item{display:flex;flex-direction:column;gap:4px}
        .wac-color-item label{font-size:11px;color:#86868b}
        
        
        /* Pro Box */
        .wac-pro-box{text-align:center;padding:40px}
        .wac-pro-box h2{font-size:18px;font-weight:600;margin:0 0 8px}
        .wac-pro-box>p{color:#86868b;margin-bottom:20px}
        .wac-pro-list{list-style:none;padding:0;margin:0 0 24px;text-align:left;display:inline-block}
        .wac-pro-list li{padding:6px 0;font-size:13px;color:#1d1d1f;position:relative;padding-left:16px}
        .wac-pro-list li::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:4px;height:4px;border-radius:50%;background:#86868b}
        .wac-pro-box .button-hero{background:#1d1d1f;color:#fff;border:none;padding:12px 28px;font-size:14px;border-radius:8px;text-decoration:none}
        .wac-pro-box .button-hero:hover{background:#000;color:#fff}
        
        /* Global Button Styles */
        .wac-btn,.wac-settings-wrap .wac-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s ease;text-decoration:none;line-height:1.4;height:auto;box-shadow:none;outline:none}
        .wac-btn:focus,.wac-settings-wrap .wac-btn:focus{outline:none;box-shadow:none}
        .wac-btn-primary,.wac-settings-wrap .wac-btn-primary{background:#007aff;color:#fff}
        .wac-btn-primary:hover,.wac-settings-wrap .wac-btn-primary:hover{background:#0056b3;color:#fff}
        .wac-btn-secondary,.wac-settings-wrap .wac-btn-secondary{background:#e5e5ea;color:#1d1d1f}
        .wac-btn-secondary:hover,.wac-settings-wrap .wac-btn-secondary:hover{background:#d1d1d6;color:#1d1d1f}
        .wac-btn-danger,.wac-settings-wrap .wac-btn-danger{background:#ff3b30;color:#fff}
        .wac-btn-danger:hover,.wac-settings-wrap .wac-btn-danger:hover{background:#d63029;color:#fff}
        .wac-btn-sm,.wac-settings-wrap .wac-btn-sm{padding:6px 12px;font-size:12px}
        .wac-btn:disabled,.wac-settings-wrap .wac-btn:disabled{opacity:.5;cursor:not-allowed}
        
        /* Locked Sections */
        .wac-settings-section.wac-locked{position:relative}
        .wac-settings-section.wac-locked::after{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,.6);pointer-events:none;border-radius:10px}
        .wac-section-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px}
        .wac-section-header h2{margin:0;display:flex;align-items:center;gap:6px}
        .wac-unlock-btn{background:#1d1d1f;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;position:relative;z-index:10;transition:all .15s ease}
        .wac-unlock-btn:hover{background:#000;color:#fff;transform:translateY(-1px);box-shadow:0 2px 4px rgba(0,0,0,.1)}
        
        /* Feature List */
        .wac-feature-list{display:flex;flex-direction:column;gap:8px;padding:16px;background:#f9f9fb;border-radius:8px;margin-top:8px}
        .wac-feature-item{display:flex;align-items:center;gap:10px;font-size:13px;color:#1d1d1f;padding:4px 0}
        .wac-feature-item .dashicons{color:#34c759;font-size:16px;width:16px;height:16px}
        
        /* Feature Preview */
        .wac-feature-preview{background:#f9f9fb;border-radius:8px;padding:40px;text-align:center;margin-top:8px}
        .wac-preview-placeholder{color:#86868b}
        .wac-preview-placeholder p{margin:12px 0 0;font-size:13px}
        
        /* Kbd */
        kbd{background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px;font-family:inherit;font-weight:500}
        
        /* Improved Section Spacing */
        .wac-settings-section{padding:24px 28px;border-bottom:1px solid #e5e5ea;transition:background .15s ease}
        .wac-settings-section:last-child{border-bottom:none}
        .wac-settings-section:hover{background:#fafafa}
        .wac-settings-section h2{font-size:16px;font-weight:600;text-transform:none;letter-spacing:0;color:#1d1d1f;margin:0 0 6px;line-height:1.3}
        .wac-settings-section>p{margin:0 0 20px;line-height:1.5}
        
        
        /* Improved Content Area */
        .wac-tab-content{background:#fff;border:1px solid #e5e5ea;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}
        
        /* Better Form Elements */
        .wac-settings-section input[type=text],.wac-settings-section input[type=url],.wac-settings-section input[type=number],.wac-settings-section select,.wac-settings-section textarea{width:100%;max-width:400px;font-family:inherit;font-size:14px;padding:10px 14px;border:1px solid #d1d1d6;border-radius:8px;background:#fff;transition:all .15s ease}
        .wac-settings-section input:focus,.wac-settings-section textarea:focus,.wac-settings-section select:focus{outline:none;border-color:#007aff;box-shadow:0 0 0 3px rgba(0,122,255,.1)}
        
        /* Better Submit Area */
        .wac-tab-content .wac-settings-form .submit{padding:20px 24px;margin:0;background:#f9f9fb;border-top:1px solid #e5e5ea;border-radius:0 0 12px 12px}
        
        /* Code styling */
        code{background:#f5f5f7;padding:2px 6px;border-radius:4px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#1d1d1f}
        </style>
        <?php
    }

    private function render_scripts() {
        ?>
        <script>
        jQuery(function($) {
            // Notification System
            window.wacShowNotification = function(message, type) {
                type = type || 'success';
                var $notif = $('#wac-notification');
                $notif.removeClass('success error info warning')
                      .addClass(type)
                      .html('<span class="wac-notification-close">&times;</span>' + message)
                      .fadeIn(200);
                
                // Auto-hide after 4 seconds
                setTimeout(function() {
                    $notif.fadeOut(200);
                }, 4000);
            };
            
            // Close notification
            $(document).on('click', '.wac-notification-close', function() {
                $(this).closest('.wac-notification').fadeOut(200);
            });
            
            // Form submit notification
            $('#wac-settings-form').on('submit', function() {
                var $form = $(this);
                var originalAction = $form.attr('action');
                
                // Intercept form submission for AJAX
                if (typeof FormData !== 'undefined') {
                    // Modern browsers - show notification after save
                    setTimeout(function() {
                        wacShowNotification('Settings saved successfully!', 'success');
                    }, 500);
                }
            });
            
            // Media Upload
            $('.wac-media-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var input = btn.siblings('input[type="url"]');
                var preview = btn.parent().siblings('.wac-media-preview');
                
                var frame = wp.media({
                    title: 'Select Image',
                    multiple: false,
                    library: { type: 'image' }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                    if (preview.length) {
                        preview.html('<img src="' + attachment.url + '">');
                    }
                    wacShowNotification('Image selected', 'success');
                });
                
                frame.open();
            });
            
        });
        </script>
        <?php
    }

    private function tab_dashboard( $opt, $is_pro ) {
        $widgets = isset( $opt['hide_dashboard_widgets'] ) ? $opt['hide_dashboard_widgets'] : array();
        $bar = isset( $opt['hide_admin_bar_items'] ) ? $opt['hide_admin_bar_items'] : array();
        ?>
        
        <!-- Dashboard Widget Builder -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Custom Widgets <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Create custom dashboard widgets with text, RSS feeds, stats, shortcuts, or personal notes.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Dashboard_Builder' ) ) : ?>
                <?php WAC_Dashboard_Builder::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Text/HTML widgets</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> RSS feed widgets</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Quick stats widget</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Personal notes widget</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Role-based visibility</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Admin Announcements -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Announcements <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Create announcements to show to all admin users on the dashboard or as notices.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Admin_Announcements' ) ) : ?>
                <?php WAC_Admin_Announcements::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Dashboard widget announcements</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Admin notice bar announcements</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Multiple styles (info, success, warning, error)</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Dismissible or persistent options</div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="wac-settings-section">
            <div class="wac-section-header">
                <div>
                    <h2>Hide Dashboard Widgets</h2>
                    <p style="color:#86868b;margin:4px 0 0;font-size:13px">
                        Hide any dashboard widget including those from themes and plugins (e.g., Elementor, WooCommerce, etc.)
                        <br><small style="color:#ff9500">💡 Tip: If widgets don't appear, visit the Dashboard page first, then return here and click "Scan Dashboard".</small>
                    </p>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" class="wac-btn wac-btn-primary wac-btn-sm" id="wac-scan-dashboard" style="white-space:nowrap">Scan Dashboard</button>
                    <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm" id="wac-refresh-widgets" style="white-space:nowrap">Refresh List</button>
                </div>
            </div>
            <div class="wac-checkbox-list">
                <?php
                // Get all registered widgets dynamically
                $all_widgets = WAC_Dashboard_Widgets::get_all_widgets();
                
                // Group widgets by source
                $core_widgets = array();
                $plugin_widgets = array();
                $theme_widgets = array();
                
                foreach ( $all_widgets as $widget_id => $widget_data ) {
                    $title = $widget_data['title'];
                    $is_core = in_array( $widget_id, array( 'welcome_panel', 'dashboard_quick_press', 'dashboard_primary', 'dashboard_right_now', 'dashboard_activity', 'dashboard_site_health' ) );
                    
                    // Try to detect plugin/theme widgets
                    $is_plugin = false;
                    $is_theme = false;
                    
                    if ( ! $is_core ) {
                        // Check if it's from a known plugin
                        $plugin_indicators = array( 'elementor', 'woocommerce', 'yoast', 'jetpack', 'wpml', 'polylang', 'acf', 'gravity', 'contact', 'form' );
                        foreach ( $plugin_indicators as $indicator ) {
                            if ( stripos( $widget_id, $indicator ) !== false || stripos( $title, $indicator ) !== false ) {
                                $is_plugin = true;
                                break;
                            }
                        }
                        
                        // If not a known plugin, might be theme
                        if ( ! $is_plugin ) {
                            $theme = wp_get_theme();
                            if ( stripos( $widget_id, $theme->get( 'TextDomain' ) ) !== false || 
                                 stripos( $title, $theme->get( 'Name' ) ) !== false ) {
                                $is_theme = true;
                            }
                        }
                    }
                    
                    if ( $is_core ) {
                        $core_widgets[ $widget_id ] = $title;
                    } elseif ( $is_plugin ) {
                        $plugin_widgets[ $widget_id ] = $title;
                    } elseif ( $is_theme ) {
                        $theme_widgets[ $widget_id ] = $title;
                    } else {
                        // Unknown source, assume plugin
                        $plugin_widgets[ $widget_id ] = $title;
                    }
                }
                
                // Display core widgets
                if ( ! empty( $core_widgets ) ) {
                    echo '<div style="margin-bottom:16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">WordPress Core</strong></div>';
                    foreach ( $core_widgets as $id => $label ) : ?>
                        <label class="wac-checkbox-item">
                            <span><?php echo esc_html( $label ); ?></span>
                            <input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" 
                                   value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $widgets ) ); ?>>
                        </label>
                    <?php endforeach;
                }
                
                // Display plugin widgets
                if ( ! empty( $plugin_widgets ) ) {
                    echo '<div style="margin:24px 0 16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">Plugins & Themes</strong></div>';
                    foreach ( $plugin_widgets as $id => $label ) : ?>
                        <label class="wac-checkbox-item">
                            <span><?php echo esc_html( $label ); ?> <small style="color:#86868b;font-size:11px">(<?php echo esc_html( $id ); ?>)</small></span>
                            <input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" 
                                   value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $widgets ) ); ?>>
                        </label>
                    <?php endforeach;
                }
                
                // Display theme widgets
                if ( ! empty( $theme_widgets ) ) {
                    echo '<div style="margin:24px 0 16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">Theme Widgets</strong></div>';
                    foreach ( $theme_widgets as $id => $label ) : ?>
                        <label class="wac-checkbox-item">
                            <span><?php echo esc_html( $label ); ?> <small style="color:#86868b;font-size:11px">(<?php echo esc_html( $id ); ?>)</small></span>
                            <input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" 
                                   value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $widgets ) ); ?>>
                        </label>
                    <?php endforeach;
                }
                
                // If no widgets found
                if ( empty( $core_widgets ) && empty( $plugin_widgets ) && empty( $theme_widgets ) ) {
                    echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;margin:16px 0">';
                    echo '<p style="margin:0 0 8px;font-size:13px;color:#856404;font-weight:500">No widgets detected yet</p>';
                    echo '<p style="margin:0;font-size:12px;color:#856404">To see all dashboard widgets (including Elementor, WooCommerce, etc.):</p>';
                    echo '<ol style="margin:8px 0 0 20px;padding:0;font-size:12px;color:#856404">';
                    echo '<li>Visit the <a href="' . admin_url() . '" target="_blank" style="color:#007aff">Dashboard page</a></li>';
                    echo '<li>Return to this page</li>';
                    echo '<li>Click "Scan Dashboard" button</li>';
                    echo '</ol>';
                    echo '<p style="margin:8px 0 0;font-size:12px;color:#856404">Or add widget IDs manually below.</p>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <!-- Manual Widget ID Input -->
            <div style="margin-top:24px;padding-top:24px;border-top:1px solid #e5e5ea">
                <h3 style="font-size:13px;font-weight:600;margin:0 0 8px">Add Custom Widget ID</h3>
                <p style="color:#86868b;font-size:12px;margin:0 0 12px">
                    If a widget is not listed above, you can hide it by entering its widget ID. Common IDs: <code>e-dashboard-overview</code> (Elementor), <code>woocommerce_dashboard_status</code> (WooCommerce)
                </p>
                <div style="display:flex;gap:8px;align-items:flex-start">
                    <input type="text" id="wac-custom-widget-id" placeholder="e.g., e-dashboard-overview" 
                           style="flex:1;padding:8px 12px;border:1px solid #d1d1d6;border-radius:6px;font-size:13px">
                    <button type="button" class="wac-btn wac-btn-secondary" id="wac-add-custom-widget" style="white-space:nowrap">Add Widget</button>
                </div>
                <div id="wac-custom-widgets-list" style="margin-top:12px">
                    <?php
                    // Show manually added widgets
                    $custom_widgets = isset( $opt['custom_dashboard_widgets'] ) ? $opt['custom_dashboard_widgets'] : array();
                    if ( ! empty( $custom_widgets ) && is_array( $custom_widgets ) ) {
                        foreach ( $custom_widgets as $custom_id ) {
                            if ( ! empty( $custom_id ) ) {
                                echo '<label class="wac-checkbox-item" style="margin-bottom:8px">';
                                echo '<span>' . esc_html( $custom_id ) . ' <small style="color:#86868b;font-size:11px">(Custom)</small></span>';
                                echo '<input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" value="' . esc_attr( $custom_id ) . '" ' . checked( in_array( $custom_id, $widgets ), true, false ) . '>';
                                echo '</label>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
            
            <script>
            jQuery(function($) {
                // Scan dashboard for widgets
                $('#wac-scan-dashboard').on('click', function() {
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text('Scanning...').prop('disabled', true);
                    
                    var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wac_scan_dashboard',
                            nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                        },
                        success: function(response) {
                            if (response && response.success) {
                                var message = response.data && response.data.message ? response.data.message : 'Scanned successfully';
                                if (response.data && response.data.note) {
                                    message += '\n\n' + response.data.note;
                                }
                                $btn.text('Scanned! Reloading...');
                                setTimeout(function() {
                                    location.reload();
                                }, 500);
                            } else {
                                var errorMsg = 'Unknown error';
                                var suggestion = '';
                                if (response && response.data) {
                                    if (response.data.message) {
                                        errorMsg = response.data.message;
                                    }
                                    if (response.data.suggestion) {
                                        suggestion = '\n\n' + response.data.suggestion;
                                    }
                                }
                                alert('Error scanning dashboard: ' + errorMsg + suggestion);
                                $btn.text(originalText).prop('disabled', false);
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = 'Network error. Please check your connection and try again.';
                            var suggestion = '\n\nTip: Visit the Dashboard page first, then return here and try again.';
                            
                            // Try to parse error response
                            if (xhr.status === 0) {
                                errorMsg = 'Connection failed. Please check your internet connection.';
                            } else if (xhr.status === 403) {
                                errorMsg = 'Permission denied. Please refresh the page and try again.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error occurred. Please try visiting the Dashboard page first, then return here.';
                            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                                if (xhr.responseJSON.data.message) {
                                    errorMsg = xhr.responseJSON.data.message;
                                }
                                if (xhr.responseJSON.data.suggestion) {
                                    suggestion = '\n\n' + xhr.responseJSON.data.suggestion;
                                }
                            } else if (xhr.responseText) {
                                try {
                                    var json = JSON.parse(xhr.responseText);
                                    if (json.data && json.data.message) {
                                        errorMsg = json.data.message;
                                    }
                                    if (json.data && json.data.suggestion) {
                                        suggestion = '\n\n' + json.data.suggestion;
                                    }
                                } catch(e) {
                                    // If response is HTML (PHP error), show generic message
                                    if (xhr.responseText.indexOf('<!DOCTYPE') !== -1 || xhr.responseText.indexOf('<html') !== -1) {
                                        errorMsg = 'Server error occurred. Please visit the Dashboard page first, then return here.';
                                    }
                                }
                            }
                            
                            alert('Error scanning dashboard: ' + errorMsg + suggestion);
                            $btn.text(originalText).prop('disabled', false);
                        }
                    });
                });
                
                // Refresh widget list
                $('#wac-refresh-widgets').on('click', function() {
                    var $btn = $(this);
                    $btn.text('Refreshing...').prop('disabled', true);
                    
                    var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wac_clear_widget_cache',
                            nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                        },
                        success: function() {
                            location.reload();
                        },
                        error: function() {
                            alert('Error refreshing list. Please try again.');
                            $btn.text('Refresh List').prop('disabled', false);
                        }
                    });
                });
                
                // Add custom widget
                $('#wac-add-custom-widget').on('click', function() {
                    var widgetId = $('#wac-custom-widget-id').val().trim();
                    if (widgetId) {
                        // Check if already exists
                        var exists = false;
                        $('#wac-custom-widgets-list input[value="' + widgetId + '"]').each(function() {
                            exists = true;
                        });
                        
                        if (!exists) {
                            var $newLabel = $('<label class="wac-checkbox-item" style="margin-bottom:8px">');
                            $newLabel.html('<span>' + $('<div>').text(widgetId).html() + ' <small style="color:#86868b;font-size:11px">(Custom)</small></span>' +
                                '<input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" value="' + $('<div>').text(widgetId).html() + '">');
                            $('#wac-custom-widgets-list').append($newLabel);
                            $('#wac-custom-widget-id').val('');
                        } else {
                            alert('This widget ID is already added.');
                        }
                    }
                });
                
                $('#wac-custom-widget-id').on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        $('#wac-add-custom-widget').click();
                    }
                });
            });
            </script>
        </div>

        <div class="wac-settings-section">
            <h2>Admin Toolbar</h2>
            <div class="wac-checkbox-list">
                <?php
                $bar_items = array(
                    'wp-logo'     => 'WordPress Logo',
                    'site-name'   => 'Site Name',
                    'updates'     => 'Updates',
                    'comments'    => 'Comments',
                    'new-content' => 'New (+)',
                    'search'      => 'Search',
                );
                foreach ( $bar_items as $id => $label ) : ?>
                    <label class="wac-checkbox-item">
                        <span><?php echo esc_html( $label ); ?></span>
                        <input type="checkbox" name="wac_settings[hide_admin_bar_items][]" 
                               value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $bar ) ); ?>>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wac-settings-section">
            <h2>General</h2>
            <div class="wac-checkbox-list">
                <label class="wac-checkbox-item">
                    <span>Hide Screen Options</span>
                    <input type="checkbox" name="wac_settings[hide_screen_options]" value="1" <?php checked( ! empty( $opt['hide_screen_options'] ) ); ?>>
                </label>
                <label class="wac-checkbox-item">
                    <span>Hide Help Tab</span>
                    <input type="checkbox" name="wac_settings[hide_help_tab]" value="1" <?php checked( ! empty( $opt['hide_help_tab'] ) ); ?>>
                </label>
                <label class="wac-checkbox-item">
                    <span>Hide All Admin Notices</span>
                    <input type="checkbox" name="wac_settings[hide_all_notices]" value="1" <?php checked( ! empty( $opt['hide_all_notices'] ) ); ?>>
                </label>
                <label class="wac-checkbox-item">
                    <span>Hide Update Notices</span>
                    <input type="checkbox" name="wac_settings[hide_update_notices]" value="1" <?php checked( ! empty( $opt['hide_update_notices'] ) ); ?>>
                </label>
            </div>
        </div>
        <?php
    }

    private function tab_productivity( $opt, $is_pro ) {
        ?>
        <!-- Command Palette -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Command Palette <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Press <kbd style="background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px">⌘/Ctrl + K</kbd> anywhere in admin to quickly search posts, pages, users, and navigate to any setting.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row">
                <div class="wac-row-label">
                    Enable Command Palette
                    <small>Uncheck to disable the command palette completely</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[command_palette_enabled]" value="1"
                           <?php checked( ! isset( $opt['command_palette_enabled'] ) || $opt['command_palette_enabled'] == '1' || $opt['command_palette_enabled'] === true ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <div class="wac-row" style="margin-top:12px">
                <div class="wac-row-label">
                    Show search icon in admin bar
                    <small>Display ⌘K button in the top admin bar</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[command_palette_show_admin_bar_icon]" value="1"
                           <?php checked( ! isset( $opt['command_palette_show_admin_bar_icon'] ) || $opt['command_palette_show_admin_bar_icon'] == '1' ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <?php else : ?>
            <div class="wac-feature-preview">
                <img src="<?php echo WAC_PLUGIN_URL; ?>assets/img/preview-command.png" alt="" onerror="this.style.display='none'">
                <div class="wac-preview-placeholder">
                    <span class="dashicons dashicons-search" style="font-size:48px;color:#86868b"></span>
                    <p>Search anything with Cmd/Ctrl + K</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Duplicate Posts -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Duplicate Posts <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                One-click duplication for posts, pages, and custom post types. Copies all content, meta, taxonomies, and featured image.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row">
                <div class="wac-row-label">
                    Enable Duplicate Posts
                    <small>Add "Duplicate" link to post actions</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[duplicate_enabled]" value="1"
                           <?php checked( ( $opt['duplicate_enabled'] ?? 1 ) == 1 ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <div class="wac-row">
                <div class="wac-row-label">
                    Show in Admin Bar
                    <small>Add duplicate button when editing posts</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[duplicate_admin_bar]" value="1"
                           <?php checked( ( $opt['duplicate_admin_bar'] ?? 1 ) == 1 ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <div class="wac-row">
                <div class="wac-row-label">
                    Enable Bulk Duplicate
                    <small>Add to bulk actions dropdown</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[duplicate_bulk]" value="1"
                           <?php checked( ( $opt['duplicate_bulk'] ?? 1 ) == 1 ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    One-click duplicate from post list
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Duplicate button in admin bar when editing
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Bulk duplicate multiple posts at once
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Copies all meta, categories, tags, featured image
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Admin Columns -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Admin Columns <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Add custom columns to post lists - Featured Image, Word Count, Post ID, Custom Fields, and more.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Admin_Columns' ) ) : ?>
                <?php WAC_Admin_Columns::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Featured Image thumbnail column
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Post ID column
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Word Count column
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Last Modified date column
                </div>
                <div class="wac-feature-item">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Custom Field columns
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_menus( $opt, $is_pro ) {
        $roles = wac_get_roles();
        unset( $roles['administrator'] );
        $role_settings = isset( $opt['role_menu_settings'] ) ? $opt['role_menu_settings'] : array();
        $redirects = isset( $opt['login_redirect'] ) ? $opt['login_redirect'] : array();
        
        $menus = WAC_Role_Manager::get_default_menu_items();
        ?>
        
        <!-- Role Editor (PRO) -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Role Editor <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Create custom roles, clone existing ones, and manage all capabilities in detail.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Role_Editor' ) ) : ?>
                <?php WAC_Role_Editor::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Create custom user roles</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Clone existing roles</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Edit all 70+ capabilities</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Delete unused roles</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Visual capability editor</div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="wac-settings-section">
            <h2>Hide Menus by Role</h2>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Hide specific admin menu items for non-admin roles.
            </p>
            <?php foreach ( $roles as $role_key => $role_name ) : 
                $hidden = isset( $role_settings[ $role_key ] ) ? $role_settings[ $role_key ] : array();
            ?>
                <div class="wac-role-block">
                    <div class="wac-role-header"><?php echo esc_html( $role_name ); ?></div>
                    <div class="wac-role-grid">
                        <?php foreach ( $menus as $slug => $name ) : ?>
                            <label>
                                <input type="checkbox" 
                                       name="wac_settings[role_menu_settings][<?php echo esc_attr( $role_key ); ?>][]" 
                                       value="<?php echo esc_attr( $slug ); ?>"
                                       <?php checked( in_array( $slug, $hidden ) ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wac-settings-section">
            <h2>Login Redirect</h2>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Redirect users to a custom URL after login based on their role.
            </p>
            <table class="form-table">
                <?php foreach ( wac_get_roles() as $key => $name ) : ?>
                    <tr>
                        <th><?php echo esc_html( $name ); ?></th>
                        <td>
                            <input type="url" name="wac_settings[login_redirect][<?php echo esc_attr( $key ); ?>]" 
                                   value="<?php echo esc_url( isset( $redirects[ $key ] ) ? $redirects[ $key ] : '' ); ?>"
                                   class="regular-text" placeholder="Default">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    private function tab_appearance( $opt, $is_pro ) {
        ?>
        
        <!-- Admin Theme -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Admin Theme <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Apply custom color schemes or enable dark mode for the entire WordPress admin.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Admin_Theme' ) ) : ?>
                <?php WAC_Admin_Theme::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Dark mode for entire admin</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> 5 color presets (Midnight, Ocean, Forest, Sunset, Custom)</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom primary/secondary colors</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Reduced eye strain at night</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- White Label Admin -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>White Label Admin <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Replace WordPress branding with your own logo, footer text, and styling.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">Enable White Label</div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[white_label_enabled]" value="1"
                           <?php checked( ! empty( $opt['white_label_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <table class="form-table">
                <tr>
                    <th>Admin Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[custom_admin_logo]" value="<?php echo esc_url( $opt['custom_admin_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['custom_admin_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['custom_admin_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;font-size:11px;color:#86868b">Recommended: 200x50px transparent PNG</p>
                    </td>
                </tr>
                <tr>
                    <th>Footer Text</th>
                    <td><input type="text" name="wac_settings[custom_footer_text]" value="<?php echo esc_attr( $opt['custom_footer_text'] ?? '' ); ?>" class="regular-text" placeholder="Powered by Your Company"></td>
                </tr>
                <tr>
                    <th>Hide WP Logo</th>
                    <td><label><input type="checkbox" name="wac_settings[hide_wp_logo]" value="1" <?php checked( ! empty( $opt['hide_wp_logo'] ) ); ?>> Remove WordPress logo from admin bar</label></td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom admin logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom footer text</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress logo from admin bar</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Remove WordPress branding completely</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Login Page Design -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Page Design <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Customize the WordPress login page with your branding.
            </p>
            <?php if ( $is_pro ) : ?>
            <table class="form-table">
                <tr>
                    <th>Login Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_logo]" value="<?php echo esc_url( $opt['login_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_bg_image]" value="<?php echo esc_url( $opt['login_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Colors</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Background</label>
                                <input type="color" name="wac_settings[login_bg_color]" value="<?php echo esc_attr( $opt['login_bg_color'] ?? '#f5f5f7' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Form BG</label>
                                <input type="color" name="wac_settings[login_form_bg]" value="<?php echo esc_attr( $opt['login_form_bg'] ?? '#ffffff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Button</label>
                                <input type="color" name="wac_settings[login_btn_color]" value="<?php echo esc_attr( $opt['login_btn_color'] ?? '#007aff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[login_text_color]" value="<?php echo esc_attr( $opt['login_text_color'] ?? '#1d1d1f' ); ?>">
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom login logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Background image or color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom button color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Form background styling</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Custom CSS -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Custom CSS <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Add custom CSS to the WordPress admin area for advanced styling.
            </p>
            <?php if ( $is_pro ) : ?>
            <textarea name="wac_settings[custom_admin_css]" rows="8" class="large-text code" placeholder="/* Your custom CSS */"><?php echo esc_textarea( $opt['custom_admin_css'] ?? '' ); ?></textarea>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Full CSS editor</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Style any admin element</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Real-time preview</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_features( $opt, $is_pro ) {
        $features = WAC_Disable_Features::get_features();
        ?>
        
        <div class="wac-settings-section">
            <h2>Disable Features</h2>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Disable WordPress features you don't need for better performance.
            </p>
            <?php foreach ( $features as $key => $f ) : ?>
                <div class="wac-row">
                    <div class="wac-row-label">
                        <?php echo esc_html( $f['label'] ); ?>
                        <small><?php echo esc_html( $f['description'] ); ?></small>
                    </div>
                    <label class="wac-switch">
                        <input type="checkbox" name="wac_settings[<?php echo esc_attr( $key ); ?>]" value="1"
                               <?php checked( ! empty( $opt[ $key ] ) ); ?>>
                        <span class="wac-switch-slider"></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wac-settings-section">
            <h2>Maintenance Mode</h2>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">
                    Enable Maintenance Mode
                    <small>Display a maintenance page to visitors while logged-in users can access the site</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[maintenance_enabled]" value="1"
                           <?php checked( ! empty( $opt['maintenance_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            
            <table class="form-table">
                <tr>
                    <th>Title</th>
                    <td><input type="text" name="wac_settings[maintenance_title]" value="<?php echo esc_attr( $opt['maintenance_title'] ?? '' ); ?>" class="regular-text" placeholder="We'll be right back"></td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td><textarea name="wac_settings[maintenance_message]" rows="2" class="large-text" placeholder="Our site is currently undergoing scheduled maintenance."><?php echo esc_textarea( $opt['maintenance_message'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Background</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Color</label>
                                <input type="color" name="wac_settings[maintenance_bg_color]" value="<?php echo esc_attr( $opt['maintenance_bg_color'] ?? '#ffffff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[maintenance_text_color]" value="<?php echo esc_attr( $opt['maintenance_text_color'] ?? '#1d1d1f' ); ?>">
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[maintenance_bg_image]" value="<?php echo esc_url( $opt['maintenance_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="Optional">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['maintenance_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['maintenance_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function tab_tools( $opt, $is_pro ) {
        ?>
        <!-- Activity Log -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Activity Log <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Track all admin actions: post edits, plugin changes, user management, and more.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Activity_Log' ) ) : ?>
                <?php WAC_Activity_Log::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Track post/page edits</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Plugin/theme activations</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> User creation/deletion</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Export/Import -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Export / Import <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Backup and migrate your settings between sites.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-export-import-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div style="padding:16px;background:#f5f5f7;border-radius:8px">
                    <h3 style="margin:0 0 4px;font-size:14px">Export</h3>
                    <p style="font-size:12px;color:#86868b;margin:0 0 12px">Download settings as JSON</p>
                    <button type="button" class="button" id="wac-export-btn">Export</button>
                </div>
                <div style="padding:16px;background:#f5f5f7;border-radius:8px">
                    <h3 style="margin:0 0 4px;font-size:14px">Import</h3>
                    <p style="font-size:12px;color:#86868b;margin:0 0 12px">Upload a settings file</p>
                    <input type="file" id="wac-import-file" accept=".json" style="display:none">
                    <button type="button" class="button" onclick="document.getElementById('wac-import-file').click();">Import</button>
                </div>
            </div>
            <?php WAC_Export_Import::render_scripts(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Export all settings to JSON</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Import settings from file</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="wac-settings-section">
            <h2>Reset</h2>
            <p style="margin:0 0 12px;color:#86868b;">Reset all settings to defaults.</p>
            <form method="post" style="display:inline">
                <?php wp_nonce_field( 'wac_reset', '_wac_reset' ); ?>
                <button type="submit" name="wac_reset" class="button" onclick="return confirm('Are you sure? This will reset all settings.');">Reset All</button>
            </form>
        </div>
        <?php
    }

    private function tab_security( $opt, $is_pro ) {
        $security = class_exists( 'WAC_Security_Tweaks' ) ? WAC_Security_Tweaks::get_options() : array();
        ?>
        
        <!-- Security Tweaks -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Security Tweaks <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Harden your WordPress installation with these security options.
            </p>
            <?php if ( $is_pro ) : ?>
                <?php foreach ( $security as $key => $s ) : ?>
                    <div class="wac-row">
                        <div class="wac-row-label">
                            <?php echo esc_html( $s['label'] ); ?>
                            <small><?php echo esc_html( $s['description'] ); ?></small>
                        </div>
                        <label class="wac-switch">
                            <input type="checkbox" name="wac_settings[<?php echo esc_attr( $key ); ?>]" value="1"
                                   <?php checked( ! empty( $opt[ $key ] ) ); ?>>
                            <span class="wac-switch-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress version</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Disable XML-RPC</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Disable file editing</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Disable author archives</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Force HTTPS admin</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Login Protection -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Protection <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Protect your login page with rate limiting and custom URL.
            </p>
            <?php if ( $is_pro ) : ?>
            <table class="form-table">
                <tr>
                    <th>Max Login Attempts</th>
                    <td><input type="number" name="wac_settings[max_login_attempts]" value="<?php echo esc_attr( $opt['max_login_attempts'] ?? 5 ); ?>" min="1" max="20" style="width:70px;"></td>
                </tr>
                <tr>
                    <th>Lockout (minutes)</th>
                    <td><input type="number" name="wac_settings[login_lockout_time]" value="<?php echo esc_attr( $opt['login_lockout_time'] ?? 15 ); ?>" min="1" max="60" style="width:70px;"></td>
                </tr>
                <tr>
                    <th>Custom Login URL</th>
                    <td>
                        <code><?php echo home_url( '/' ); ?></code>
                        <input type="text" name="wac_settings[custom_login_url]" value="<?php echo esc_attr( $opt['custom_login_url'] ?? '' ); ?>" style="width:120px;" placeholder="my-login">
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Limit failed login attempts</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Temporary IP lockout</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom login URL (hide wp-login.php)</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Login History -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login History <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Track all login attempts with IP address, browser, and device information.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Login_History' ) ) : ?>
                <?php WAC_Login_History::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Track successful and failed logins</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> IP address logging</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Browser and device detection</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Geolocation (country/city)</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Activity Log -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Activity Log <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Track all admin actions: post edits, plugin changes, user management, and more.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Activity_Log' ) ) : ?>
                <?php WAC_Activity_Log::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Track post/page edits</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Plugin/theme activations</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> User creation/deletion</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Settings changes</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Export logs to CSV</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_branding( $opt, $is_pro ) {
        ?>
        
        <!-- White Label Admin -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>White Label Admin <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Replace WordPress branding with your own logo, footer text, and styling.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">Enable White Label</div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[white_label_enabled]" value="1"
                           <?php checked( ! empty( $opt['white_label_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <table class="form-table">
                <tr>
                    <th>Admin Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[custom_admin_logo]" value="<?php echo esc_url( $opt['custom_admin_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['custom_admin_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['custom_admin_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;font-size:11px;color:#86868b">Recommended: 200x50px transparent PNG</p>
                    </td>
                </tr>
                <tr>
                    <th>Footer Text</th>
                    <td><input type="text" name="wac_settings[custom_footer_text]" value="<?php echo esc_attr( $opt['custom_footer_text'] ?? '' ); ?>" class="regular-text" placeholder="Powered by Your Company"></td>
                </tr>
                <tr>
                    <th>Hide WP Logo</th>
                    <td><label><input type="checkbox" name="wac_settings[hide_wp_logo]" value="1" <?php checked( ! empty( $opt['hide_wp_logo'] ) ); ?>> Remove WordPress logo from admin bar</label></td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom admin logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom footer text</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress logo from admin bar</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Remove WordPress branding completely</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Login Page Design -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Page Design <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Customize the WordPress login page with your branding.
            </p>
            <?php if ( $is_pro ) : ?>
            <table class="form-table">
                <tr>
                    <th>Login Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_logo]" value="<?php echo esc_url( $opt['login_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_bg_image]" value="<?php echo esc_url( $opt['login_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Colors</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Background</label>
                                <input type="color" name="wac_settings[login_bg_color]" value="<?php echo esc_attr( $opt['login_bg_color'] ?? '#f5f5f7' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Form BG</label>
                                <input type="color" name="wac_settings[login_form_bg]" value="<?php echo esc_attr( $opt['login_form_bg'] ?? '#ffffff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Button</label>
                                <input type="color" name="wac_settings[login_btn_color]" value="<?php echo esc_attr( $opt['login_btn_color'] ?? '#007aff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[login_text_color]" value="<?php echo esc_attr( $opt['login_text_color'] ?? '#1d1d1f' ); ?>">
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom login logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Background image or color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom button color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Form background styling</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Custom CSS -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Custom CSS <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Add custom CSS to the WordPress admin area for advanced styling.
            </p>
            <?php if ( $is_pro ) : ?>
            <textarea name="wac_settings[custom_admin_css]" rows="8" class="large-text code" placeholder="/* Your custom CSS */"><?php echo esc_textarea( $opt['custom_admin_css'] ?? '' ); ?></textarea>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Full CSS editor</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Style any admin element</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Real-time preview</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function pro_upsell() {
        ?>
        <div class="wac-pro-box">
            <h2>Pro Features</h2>
            <p>Upgrade to unlock all premium features.</p>
            <ul class="wac-pro-list">
                <li>Security Hardening</li>
                <li>Login Protection</li>
                <li>Custom Login URL</li>
                <li>White Label Mode</li>
                <li>Login Page Designer</li>
                <li>Activity Logging</li>
                <li>Export/Import</li>
            </ul>
            <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="button button-hero">Upgrade to Pro</a>
        </div>
        <?php
    }

    /**
     * Tab: Cleanup - Database, Media, Maintenance
     */
    private function tab_cleanup( $opt, $is_pro ) {
        // Database Cleanup
        $this->tab_database_cleanup( $opt, $is_pro );
        
        // Media Cleanup
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        $this->tab_media_cleanup( $opt, $is_pro );
        echo '</div>';
        
        // Maintenance Mode
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        $this->tab_maintenance( $opt, $is_pro );
        echo '</div>';
    }

    /**
     * Tab: Menus & Roles - Menu Editor, Role Editor, Role-based menus
     */
    private function tab_menus_roles( $opt, $is_pro ) {
        // Menu Editor
        if ( $is_pro && class_exists( 'WAC_Menu_Editor' ) ) {
            WAC_Menu_Editor::render_ui();
        } else {
            echo '<div class="wac-settings-section ' . ( ! $is_pro ? 'wac-locked' : '' ) . '">';
            echo '<div class="wac-section-header"><h2>Admin Menu Editor <span class="wac-pro-badge-small">PRO</span></h2>';
            if ( ! $is_pro ) {
                echo '<a href="' . esc_url( wac_fs()->get_upgrade_url() ) . '" class="wac-unlock-btn">Unlock</a>';
            }
            echo '</div>';
            echo '<p style="color:#86868b;margin:-8px 0 16px;font-size:13px">Drag to reorder menu items, rename them, change icons, and control visibility per role.</p>';
            echo '<div class="wac-feature-list">';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Drag & drop menu reordering</div>';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Rename any menu item</div>';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Change menu icons</div>';
            echo '</div></div>';
        }
        
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        
        // Role Editor
        if ( $is_pro && class_exists( 'WAC_Role_Editor' ) ) {
            WAC_Role_Editor::render_ui();
        } else {
            echo '<div class="wac-settings-section ' . ( ! $is_pro ? 'wac-locked' : '' ) . '">';
            echo '<div class="wac-section-header"><h2>Role Editor <span class="wac-pro-badge-small">PRO</span></h2>';
            if ( ! $is_pro ) {
                echo '<a href="' . esc_url( wac_fs()->get_upgrade_url() ) . '" class="wac-unlock-btn">Unlock</a>';
            }
            echo '</div>';
            echo '<p style="color:#86868b;margin:-8px 0 16px;font-size:13px">Create custom roles, clone existing ones, and manage all capabilities in detail.</p>';
            echo '<div class="wac-feature-list">';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Create custom user roles</div>';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Clone existing roles</div>';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Edit all 70+ capabilities</div>';
            echo '</div></div>';
        }
        
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        
        // Role-based menus
        $this->tab_menus( $opt, $is_pro );
        
        echo '</div></div>';
    }

    /**
     * Tab: Customize - Appearance, Menus, Productivity
     */
    private function tab_customize( $opt, $is_pro ) {
        // Appearance section
        $this->tab_appearance( $opt, $is_pro );
        
        // Menus section
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        $this->tab_menus( $opt, $is_pro );
        echo '</div>';
        
        // Productivity section
        echo '<div style="margin-top:32px;padding-top:32px;border-top:2px solid #e5e5ea">';
        $this->tab_productivity( $opt, $is_pro );
        echo '</div>';
    }

    // Individual feature tabs
    private function tab_dashboard_widgets( $opt, $is_pro ) {
        $widgets = isset( $opt['hide_dashboard_widgets'] ) ? $opt['hide_dashboard_widgets'] : array();
        // Extract only dashboard widgets section from tab_dashboard - start from line 561
        $this->tab_dashboard( $opt, $is_pro );
    }

    private function tab_admin_menu( $opt, $is_pro ) {
        if ( $is_pro && class_exists( 'WAC_Menu_Editor' ) ) {
            WAC_Menu_Editor::render_ui();
        } else {
            $this->pro_upsell();
        }
    }

    private function tab_admin_theme( $opt, $is_pro ) {
        if ( $is_pro && class_exists( 'WAC_Admin_Theme' ) ) {
            WAC_Admin_Theme::render_ui();
        } else {
            $this->pro_upsell();
        }
    }

    private function tab_white_label( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>White Label Admin <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Replace WordPress branding with your own logo, footer text, and styling.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">Enable White Label</div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[white_label_enabled]" value="1"
                           <?php checked( ! empty( $opt['white_label_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <table class="form-table">
                <tr>
                    <th>Admin Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[custom_admin_logo]" value="<?php echo esc_url( $opt['custom_admin_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['custom_admin_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['custom_admin_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;font-size:11px;color:#86868b">Recommended: 200x50px transparent PNG</p>
                    </td>
                </tr>
                <tr>
                    <th>Footer Text</th>
                    <td><input type="text" name="wac_settings[custom_footer_text]" value="<?php echo esc_attr( $opt['custom_footer_text'] ?? '' ); ?>" class="regular-text" placeholder="Powered by Your Company"></td>
                </tr>
                <tr>
                    <th>Hide WP Logo</th>
                    <td><label><input type="checkbox" name="wac_settings[hide_wp_logo]" value="1" <?php checked( ! empty( $opt['hide_wp_logo'] ) ); ?>> Remove WordPress logo from admin bar</label></td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom admin logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom footer text</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress logo from admin bar</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_login_page( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Page Design <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Customize the WordPress login page with your branding.
            </p>
            <?php if ( $is_pro ) : ?>
            <table class="form-table">
                <tr>
                    <th>Login Logo</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_logo]" value="<?php echo esc_url( $opt['login_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[login_bg_image]" value="<?php echo esc_url( $opt['login_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="https://">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['login_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['login_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Colors</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Background</label>
                                <input type="color" name="wac_settings[login_bg_color]" value="<?php echo esc_attr( $opt['login_bg_color'] ?? '#f5f5f7' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Form BG</label>
                                <input type="color" name="wac_settings[login_form_bg]" value="<?php echo esc_attr( $opt['login_form_bg'] ?? '#ffffff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Button</label>
                                <input type="color" name="wac_settings[login_btn_color]" value="<?php echo esc_attr( $opt['login_btn_color'] ?? '#007aff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[login_text_color]" value="<?php echo esc_attr( $opt['login_text_color'] ?? '#1d1d1f' ); ?>">
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom login logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Background image or color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom button color</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_command_palette( $opt, $is_pro ) {
        // Extract from tab_productivity
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Command Palette <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Press <kbd style="background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px">⌘/Ctrl + K</kbd> anywhere in admin to quickly search posts, pages, users, and navigate to any setting.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row">
                <div class="wac-row-label">
                    Enable Command Palette
                    <small>Show search icon in admin bar</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[command_palette_enabled]" value="1"
                           <?php checked( ( $opt['command_palette_enabled'] ?? 1 ) == 1 ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Quick search with Cmd/Ctrl + K</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Search posts, pages, users</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Navigate to any setting</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_duplicate_post( $opt, $is_pro ) {
        // Extract from tab_productivity
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Duplicate Posts <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                One-click duplication for posts, pages, and custom post types. Copies all content, meta, taxonomies, and featured image.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row">
                <div class="wac-row-label">
                    Enable Duplicate Posts
                    <small>Add "Duplicate" link to post actions</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[duplicate_enabled]" value="1"
                           <?php checked( ( $opt['duplicate_enabled'] ?? 1 ) == 1 ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> One-click duplicate from post list</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Duplicate button in admin bar</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Bulk duplicate multiple posts</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_role_editor( $opt, $is_pro ) {
        if ( $is_pro && class_exists( 'WAC_Role_Editor' ) ) {
            WAC_Role_Editor::render_ui();
        } else {
            $this->pro_upsell();
        }
    }

    private function tab_admin_columns( $opt, $is_pro ) {
        if ( $is_pro && class_exists( 'WAC_Admin_Columns' ) ) {
            WAC_Admin_Columns::render_ui();
        } else {
            $this->pro_upsell();
        }
    }

    private function tab_disable_features( $opt, $is_pro ) {
        $features = WAC_Disable_Features::get_features();
        ?>
        <div class="wac-settings-section">
            <h2>Disable Features</h2>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Disable WordPress features you don't need for better performance.
            </p>
            <?php foreach ( $features as $key => $f ) : ?>
                <div class="wac-row">
                    <div class="wac-row-label">
                        <?php echo esc_html( $f['label'] ); ?>
                        <small><?php echo esc_html( $f['description'] ); ?></small>
                    </div>
                    <label class="wac-switch">
                        <input type="checkbox" name="wac_settings[<?php echo esc_attr( $key ); ?>]" value="1"
                               <?php checked( ! empty( $opt[ $key ] ) ); ?>>
                        <span class="wac-switch-slider"></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function tab_maintenance( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section">
            <h2>Maintenance Mode</h2>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">
                    Enable Maintenance Mode
                    <small>Display a maintenance page to visitors while logged-in users can access the site</small>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[maintenance_enabled]" value="1"
                           <?php checked( ! empty( $opt['maintenance_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            
            <table class="form-table">
                <tr>
                    <th>Title</th>
                    <td><input type="text" name="wac_settings[maintenance_title]" value="<?php echo esc_attr( $opt['maintenance_title'] ?? '' ); ?>" class="regular-text" placeholder="We'll be right back"></td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td><textarea name="wac_settings[maintenance_message]" rows="2" class="large-text" placeholder="Our site is currently undergoing scheduled maintenance."><?php echo esc_textarea( $opt['maintenance_message'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Background</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Color</label>
                                <input type="color" name="wac_settings[maintenance_bg_color]" value="<?php echo esc_attr( $opt['maintenance_bg_color'] ?? '#ffffff' ); ?>">
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[maintenance_text_color]" value="<?php echo esc_attr( $opt['maintenance_text_color'] ?? '#1d1d1f' ); ?>">
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[maintenance_bg_image]" value="<?php echo esc_url( $opt['maintenance_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="Optional">
                            <button type="button" class="wac-media-btn">Select</button>
                        </div>
                        <?php if ( ! empty( $opt['maintenance_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['maintenance_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function tab_database_cleanup( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section">
            <h2>Database Cleanup</h2>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Clean up your database by removing revisions, drafts, spam, and optimizing tables.
            </p>
            <?php WAC_Performance_Cleaner::render_ui(); ?>
        </div>
        <?php
    }

    private function tab_media_cleanup( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Media Cleanup <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Find and delete unused media files to free up disk space.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Media_Cleanup' ) ) : ?>
                <?php WAC_Media_Cleanup::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Scan for unattached media</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Find orphaned thumbnails</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Preview before deleting</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_security_tweaks( $opt, $is_pro ) {
        $security = class_exists( 'WAC_Security_Tweaks' ) ? WAC_Security_Tweaks::get_options() : array();
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Security Tweaks <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Harden your WordPress installation with these security options.
            </p>
            <?php if ( $is_pro ) : ?>
                <?php foreach ( $security as $key => $s ) : ?>
                    <div class="wac-row">
                        <div class="wac-row-label">
                            <?php echo esc_html( $s['label'] ); ?>
                            <small><?php echo esc_html( $s['description'] ); ?></small>
                        </div>
                        <label class="wac-switch">
                            <input type="checkbox" name="wac_settings[<?php echo esc_attr( $key ); ?>]" value="1"
                                   <?php checked( ! empty( $opt[ $key ] ) ); ?>>
                            <span class="wac-switch-slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress version</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Disable XML-RPC</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Disable file editing</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_activity_log( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Activity Log <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Track all admin actions: post edits, plugin changes, user management, and more.
            </p>
            <?php if ( $is_pro && class_exists( 'WAC_Activity_Log' ) ) : ?>
                <?php WAC_Activity_Log::render_ui(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Track post/page edits</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Plugin/theme activations</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> User creation/deletion</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function tab_export_import( $opt, $is_pro ) {
        ?>
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Export / Import <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Backup and migrate your settings between sites.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-export-import-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div style="padding:16px;background:#f5f5f7;border-radius:8px">
                    <h3 style="margin:0 0 4px;font-size:14px">Export</h3>
                    <p style="font-size:12px;color:#86868b;margin:0 0 12px">Download settings as JSON</p>
                    <button type="button" class="button" id="wac-export-btn">Export</button>
                </div>
                <div style="padding:16px;background:#f5f5f7;border-radius:8px">
                    <h3 style="margin:0 0 4px;font-size:14px">Import</h3>
                    <p style="font-size:12px;color:#86868b;margin:0 0 12px">Upload a settings file</p>
                    <input type="file" id="wac-import-file" accept=".json" style="display:none">
                    <button type="button" class="button" onclick="document.getElementById('wac-import-file').click();">Import</button>
                </div>
            </div>
            <?php WAC_Export_Import::render_scripts(); ?>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Export all settings to JSON</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Import settings from file</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
