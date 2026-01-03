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
        add_action( 'admin_init', array( $this, 'handle_settings_redirect' ), 999 );
    }

    public function add_menu() {
        $hook = add_menu_page(
            'Admin Toolkit',
            'Admin Toolkit',
            'manage_options',
            'devforge-admin-cleaner',
            array( $this, 'render_page' ),
            WAC_PLUGIN_URL . 'assets/img/icon-menu.svg',
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
        
        // Preserve tab and subtab after form submit - use high priority
        add_filter( 'wp_redirect', array( $this, 'preserve_tab_on_redirect' ), 999, 2 );
    }
    
    /**
     * Handle settings redirect to preserve tab and subtab
     * This intercepts the redirect after settings save
     */
    public function handle_settings_redirect() {
        // Only run when settings are being saved
        if ( ! isset( $_POST['option_page'] ) || $_POST['option_page'] !== 'wac_settings_group' ) {
            return;
        }
        
        // Check if this is a settings update - WordPress submit button can have different names
        // Check for any submit button or wac_settings data
        $is_submit = false;
        if ( isset( $_POST['submit'] ) || isset( $_POST['wac_settings'] ) ) {
            $is_submit = true;
        }
        // Also check for any button that starts with 'submit'
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'submit' ) === 0 ) {
                $is_submit = true;
                break;
            }
        }
        
        if ( ! $is_submit ) {
            return;
        }
        
        // Also add admin_notices as fallback
        add_action( 'admin_notices', array( $this, 'redirect_after_save' ), 1 );
    }
    
    /**
     * Redirect after settings save with tab/subtab preserved
     */
    public function redirect_after_save() {
        // Only redirect once
        static $redirected = false;
        if ( $redirected ) {
            return;
        }
        $redirected = true;
        
        // Build redirect URL
        $redirect_url = admin_url( 'admin.php?page=devforge-admin-cleaner&settings-updated=true' );
        
        // Preserve tab parameter
        if ( isset( $_POST['tab'] ) ) {
            $redirect_url = add_query_arg( 'tab', sanitize_key( $_POST['tab'] ), $redirect_url );
        }
        
        // Preserve subtab parameter
        $subtab = null;
        if ( isset( $_POST['subtab'] ) && ! empty( $_POST['subtab'] ) ) {
            $subtab = sanitize_key( $_POST['subtab'] );
            $redirect_url = add_query_arg( 'subtab', $subtab, $redirect_url );
        }
        
        // Use JavaScript redirect with hash to avoid conflicts with WordPress redirect
        // Add hash AFTER the redirect URL is built
        if ( $subtab ) {
            $redirect_url .= '#' . $subtab;
        }
        
        // Use JavaScript redirect to avoid conflicts with WordPress redirect
        echo '<script>
        (function() {
            var currentUrl = window.location.href;
            var targetUrl = "' . esc_js( $redirect_url ) . '";
            
            // Only redirect if we\'re not already on the target URL
            if (currentUrl.indexOf("settings-updated=true") === -1 || 
                currentUrl.indexOf("subtab=' . ( $subtab ? esc_js( $subtab ) : '' ) . '") === -1) {
                window.location.href = targetUrl;
            } else {
                // If we\'re already on the page, just update the hash
                if (window.location.hash !== "#' . ( $subtab ? esc_js( $subtab ) : '' ) . '") {
                    window.location.hash = "#' . ( $subtab ? esc_js( $subtab ) : '' ) . '";
                    // Force a reload to ensure subtab is activated
                    setTimeout(function() {
                        window.location.reload();
                    }, 100);
                }
            }
        })();
        </script>';
    }
    
    /**
     * Preserve tab and subtab parameters in redirect URL after settings save
     */
    public function preserve_tab_on_redirect( $location, $status ) {
        // Only modify redirects for our settings page
        if ( strpos( $location, 'devforge-admin-cleaner' ) === false ) {
            return $location;
        }
        
        // Preserve tab parameter
        if ( isset( $_POST['tab'] ) ) {
            $location = add_query_arg( 'tab', sanitize_key( $_POST['tab'] ), $location );
        }
        
        // Preserve subtab parameter (for dashboard tab)
        if ( isset( $_POST['subtab'] ) && ! empty( $_POST['subtab'] ) ) {
            $subtab = sanitize_key( $_POST['subtab'] );
            $location = add_query_arg( 'subtab', $subtab, $location );
            // Remove existing hash if any, then add new hash
            $location = preg_replace( '/#.*$/', '', $location );
            $location .= '#' . $subtab;
        }
        
        return $location;
    }
    
    /**
     * Show success notice after settings save
     */
    public function settings_saved_notice() {
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'devforge-admin-cleaner' ) {
                // Show notification after page fully loads
                // Wait for both DOM and scripts to be ready
                echo '<script>
                (function() {
                    function showSavedNotification() {
                        if (typeof jQuery !== "undefined" && typeof wacShowNotification !== "undefined") {
                            jQuery(function($) {
                                // Wait a bit more to ensure everything is ready
                                setTimeout(function() {
                                    wacShowNotification("Settings saved successfully!", "success");
                                }, 500);
                            });
                        } else {
                            // If jQuery or function not ready yet, wait and try again
                            setTimeout(showSavedNotification, 100);
                        }
                    }
                    // Start when DOM is ready
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", showSavedNotification);
                    } else {
                        // DOM already loaded, wait a bit for scripts
                        setTimeout(showSavedNotification, 200);
                    }
                })();
                </script>';
            }
        }
    }

    public function handle_reset() {
        if ( isset( $_POST['wac_reset'] ) && check_admin_referer( 'wac_reset', '_wac_reset' ) ) {
            // Delete all plugin options
            delete_option( 'wac_settings' );
            delete_option( 'wac_menu_editor' );
            delete_option( 'wac_announcements' );
            delete_option( 'wac_custom_widgets' );
            delete_option( 'wac_admin_theme' );
            delete_option( 'wac_admin_columns' );
            
            // Clear any transients
            global $wpdb;
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wac_%'" );
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wac_%'" );
            
            // Redirect to prevent resubmission
            wp_redirect( admin_url( 'admin.php?page=devforge-admin-cleaner&tab=tools&subtab=tools-reset&reset=1' ) );
            exit;
        }
    }

    public function sanitize( $input ) {
        // CRITICAL: Handle null input (can happen during option reset or other operations)
        if ( ! is_array( $input ) ) {
            $input = array();
        }
        
        $clean = array();
        
        // Get existing settings BEFORE processing input
        // This is critical for preserving custom widget hidden states
        $existing = get_option( 'wac_settings', array() );
        $existing_hidden = isset( $existing['hide_dashboard_widgets'] ) ? $existing['hide_dashboard_widgets'] : array();
        $existing_custom = isset( $existing['custom_dashboard_widgets'] ) ? $existing['custom_dashboard_widgets'] : array();
        
        // Arrays
        $arrays = array( 'hide_dashboard_widgets', 'hide_admin_bar_items' );
        foreach ( $arrays as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? array_map( 'sanitize_text_field', $input[ $key ] ) : array();
        }
        
        // CRITICAL: DON'T preserve hidden state of custom widgets that were deleted
        // First, get the new custom_dashboard_widgets array (will be processed later, but we need to check it now)
        $new_custom_temp = array();
        if ( isset( $input['custom_dashboard_widgets'] ) ) {
            if ( is_string( $input['custom_dashboard_widgets'] ) && ! empty( $input['custom_dashboard_widgets'] ) ) {
                $widget_ids = explode( ',', $input['custom_dashboard_widgets'] );
                $new_custom_temp = array_map( 'sanitize_key', array_filter( array_map( 'trim', $widget_ids ) ) );
            } elseif ( is_array( $input['custom_dashboard_widgets'] ) ) {
                // CRITICAL: Even if array is empty, we still process it (empty array means all widgets were deleted)
                $new_custom_temp = array_map( 'sanitize_key', array_filter( $input['custom_dashboard_widgets'] ) );
            }
        }
        
        // CRITICAL: Remove ALL custom widgets from hide_dashboard_widgets that are NOT in new_custom_temp
        // This ensures deleted widgets are completely removed
        if ( ! empty( $clean['hide_dashboard_widgets'] ) && is_array( $clean['hide_dashboard_widgets'] ) ) {
            $clean['hide_dashboard_widgets'] = array_values( array_filter( $clean['hide_dashboard_widgets'], function( $widget_id ) use ( $new_custom_temp, $existing_custom ) {
                // If this widget was in existing_custom but not in new_custom_temp, it was deleted - remove it
                if ( ! empty( $existing_custom ) && in_array( $widget_id, $existing_custom, true ) ) {
                    // This was a custom widget - only keep it if it's still in new_custom_temp
                    return in_array( $widget_id, $new_custom_temp, true );
                }
                // Not a custom widget, keep it
                return true;
            } ) );
        }
        
        // Only preserve hidden state for widgets that are STILL in custom_dashboard_widgets
        if ( ! empty( $existing_custom ) && is_array( $existing_custom ) && ! empty( $new_custom_temp ) ) {
            // Get submitted hidden widgets (normalized for comparison)
            $submitted_hidden_normalized = array();
            if ( ! empty( $clean['hide_dashboard_widgets'] ) && is_array( $clean['hide_dashboard_widgets'] ) ) {
                foreach ( $clean['hide_dashboard_widgets'] as $w ) {
                    $submitted_hidden_normalized[] = strtolower( trim( $w ) );
                }
            }
            
            foreach ( $existing_custom as $custom_id ) {
                $custom_id = sanitize_key( $custom_id );
                if ( empty( $custom_id ) ) {
                    continue;
                }
                
                // CRITICAL: Skip if this widget was deleted from custom_dashboard_widgets
                if ( ! in_array( $custom_id, $new_custom_temp, true ) ) {
                    // Widget was deleted - don't add it to hide_dashboard_widgets
                    continue;
                }
                
                $custom_id_normalized = strtolower( trim( $custom_id ) );
                
                // If this custom widget was previously hidden
                if ( in_array( $custom_id_normalized, array_map( 'strtolower', $existing_hidden ) ) ) {
                    // Check if user explicitly unchecked it (not in submitted hidden list)
                    if ( ! in_array( $custom_id_normalized, $submitted_hidden_normalized ) ) {
                        // Widget was in form but unchecked - user wants it visible
                        continue;
                    } else {
                        // Widget is checked in form - keep it hidden
                        if ( ! in_array( $custom_id, $clean['hide_dashboard_widgets'] ) ) {
                            $clean['hide_dashboard_widgets'][] = $custom_id;
                        }
                    }
                }
            }
            // Remove duplicates and re-index array
            $clean['hide_dashboard_widgets'] = array_values( array_unique( $clean['hide_dashboard_widgets'] ) );
            
            // CRITICAL: Remove wac_announcements from hide_dashboard_widgets
            // Announcements widget has its own enabled/disabled control, so it shouldn't be in this list
            $clean['hide_dashboard_widgets'] = array_values( array_filter( $clean['hide_dashboard_widgets'], function( $widget_id ) {
                return $widget_id !== 'wac_announcements';
            } ) );
        }
        
        // Role menu
        $clean['role_menu_settings'] = array();
        if ( isset( $input['role_menu_settings'] ) && is_array( $input['role_menu_settings'] ) ) {
            foreach ( $input['role_menu_settings'] as $role => $menus ) {
                $clean['role_menu_settings'][ sanitize_key( $role ) ] = is_array( $menus ) ? array_map( 'sanitize_text_field', $menus ) : array();
            }
        }
        
        // Login redirect - preserve existing values if not in form submission
        if ( isset( $input['login_redirect'] ) && is_array( $input['login_redirect'] ) ) {
            $clean['login_redirect'] = array();
            foreach ( $input['login_redirect'] as $role => $url ) {
                $clean['login_redirect'][ sanitize_key( $role ) ] = esc_url_raw( $url );
            }
        } else {
            // Preserve existing redirects if not in form submission
            $clean['login_redirect'] = isset( $existing['login_redirect'] ) && is_array( $existing['login_redirect'] ) ? $existing['login_redirect'] : array();
        }
        
        // Checkboxes
        $checkboxes = array(
            'hide_screen_options', 'hide_help_tab', 'hide_all_notices', 'hide_update_notices',
            'hide_admin_bar_in_admin', 'hide_admin_bar_frontend',
            'disable_comments', 'disable_emojis', 'disable_rss', 'disable_xmlrpc',
            'disable_rest_api', 'disable_gutenberg', 'remove_wp_version',
            'maintenance_enabled', 'white_label_enabled', 'hide_wp_logo',
            'disable_file_editor', 'hide_login_errors', 'disable_author_archives',
            'limit_login_attempts', 'force_strong_passwords',
            'command_palette_enabled', 'command_palette_show_admin_bar_icon',
            'duplicate_enabled', 'duplicate_admin_bar', 'duplicate_bulk',
        );
        // Define all tabs with their field indicators (any field from this tab indicates we're in that tab)
        $tab_indicators = array(
            'dashboard' => array( 'hide_dashboard_widgets', 'custom_dashboard_widgets', 'hide_screen_options', 'hide_help_tab', 'hide_all_notices', 'hide_update_notices', 'hide_admin_bar_in_admin', 'hide_admin_bar_frontend' ),
            'white_label' => array( 'custom_admin_logo', 'custom_footer_text', 'hide_wp_logo', 'white_label_enabled' ),
            'login' => array( 'login_logo', 'login_bg_image', 'login_bg_color', 'login_btn_text', 'login_hide_remember', 'login_hide_lost_password', 'login_hide_back_to_site', 'login_hide_register', 'login_form_shadow', 'login_form_width', 'login_custom_message', 'login_form_bg', 'login_text_color', 'login_bg_position', 'login_bg_size', 'login_bg_repeat', 'login_bg_overlay', 'login_bg_overlay_opacity' ),
            'disable_features' => array( 'disable_comments', 'disable_emojis', 'disable_rss', 'disable_xmlrpc', 'disable_rest_api', 'disable_gutenberg', 'remove_wp_version' ),
            'security' => array( 'disable_file_editor', 'hide_login_errors', 'disable_author_archives', 'limit_login_attempts', 'force_strong_passwords' ),
            'productivity' => array( 'command_palette_enabled', 'command_palette_show_admin_bar_icon', 'duplicate_enabled', 'duplicate_admin_bar', 'duplicate_bulk' ),
            'tools' => array( 'maintenance_enabled', 'maintenance_title', 'maintenance_message', 'maintenance_icon' ),
        );
        
        // Define checkbox groups by tab
        $tab_checkboxes = array(
            'dashboard' => array( 'hide_screen_options', 'hide_help_tab', 'hide_all_notices', 'hide_update_notices', 'hide_admin_bar_in_admin', 'hide_admin_bar_frontend' ),
            'white_label' => array( 'white_label_enabled', 'hide_wp_logo' ),
            'login' => array( 'login_hide_remember', 'login_hide_lost_password', 'login_hide_back_to_site', 'login_hide_register', 'login_form_shadow' ),
            'disable_features' => array( 'disable_comments', 'disable_emojis', 'disable_rss', 'disable_xmlrpc', 'disable_rest_api', 'disable_gutenberg', 'remove_wp_version' ),
            'security' => array( 'disable_file_editor', 'hide_login_errors', 'disable_author_archives', 'limit_login_attempts', 'force_strong_passwords' ),
            'productivity' => array( 'command_palette_enabled', 'command_palette_show_admin_bar_icon', 'duplicate_enabled', 'duplicate_admin_bar', 'duplicate_bulk' ),
            'tools' => array( 'maintenance_enabled' ),
        );
        
        // Determine which tab we're in by checking if any field from that tab is in the form
        $current_tab = null;
        foreach ( $tab_indicators as $tab => $indicators ) {
            foreach ( $indicators as $indicator ) {
                if ( isset( $input[ $indicator ] ) ) {
                    $current_tab = $tab;
                    break 2; // Break out of both loops
                }
            }
        }
        
        // Also check $_POST['tab'] as fallback (from hidden input in form)
        if ( $current_tab === null && isset( $_POST['tab'] ) ) {
            $current_tab = sanitize_key( $_POST['tab'] );
        }
        
        // Special handling for maintenance_enabled: check if we're in tools tab
        $is_maintenance_tab = false;
        if ( $current_tab === 'tools' ) {
            $is_maintenance_tab = true;
        } else {
            // Fallback: if any maintenance field is submitted, we're in maintenance tab
            $maintenance_fields = array( 'maintenance_title', 'maintenance_message', 'maintenance_bg_image', 'maintenance_bg_color', 'maintenance_text_color' );
            foreach ( $maintenance_fields as $field ) {
                if ( isset( $input[ $field ] ) ) {
                    $is_maintenance_tab = true;
                    break;
                }
            }
        }
        
        // CRITICAL: Special handling for maintenance_enabled BEFORE processing other checkboxes
        // Now with hidden input, maintenance_enabled will always be in $input (either 0 or 1)
        // But we still need to handle the case when it's disabled (though hidden input should still send 0)
        // Check $_POST['tab'] directly as additional safety
        if ( isset( $_POST['tab'] ) ) {
            $post_tab = sanitize_key( $_POST['tab'] );
            if ( $post_tab === 'tools' ) {
                // We're in maintenance tab - maintenance_enabled should always be in $input now (hidden input)
                // But if it's not (edge case), set to 0
                if ( ! isset( $input['maintenance_enabled'] ) ) {
                    $clean['maintenance_enabled'] = 0;
                } else {
                    // Use the value from form (hidden input sends 0, checkbox sends 1)
                    $clean['maintenance_enabled'] = ! empty( $input['maintenance_enabled'] ) ? 1 : 0;
                }
            }
        }
        
        foreach ( $checkboxes as $key ) {
            // Skip maintenance_enabled if we already processed it above
            if ( $key === 'maintenance_enabled' && isset( $clean['maintenance_enabled'] ) ) {
                continue;
            }
            
            // If checkbox is in form submission, use its value
            if ( isset( $input[ $key ] ) ) {
                $clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
            } else {
                // Special handling for maintenance_enabled (if not already processed above)
                if ( $key === 'maintenance_enabled' && isset( $_POST['tab'] ) ) {
                    $post_tab = sanitize_key( $_POST['tab'] );
                    if ( $post_tab === 'tools' ) {
                        // We're in maintenance tab and checkbox is not in form = unchecked = 0
                        $clean[ $key ] = 0;
                        continue;
                    }
                }
                
                // Check which tab this checkbox belongs to
                $checkbox_tab = null;
                foreach ( $tab_checkboxes as $tab => $checkbox_list ) {
                    if ( in_array( $key, $checkbox_list ) ) {
                        $checkbox_tab = $tab;
                        break;
                    }
                }
                
                // If checkbox belongs to current tab and we're in that tab, it means it's unchecked (0)
                if ( $checkbox_tab !== null && $checkbox_tab === $current_tab ) {
                    $clean[ $key ] = 0;
                } else {
                    // Preserve existing value if checkbox not in current form (different tab)
                    $clean[ $key ] = ! empty( $existing[ $key ] ) ? 1 : 0;
                }
            }
        }
        
        // If admin bar is hidden in wp-admin, disable admin bar icon option
        // BUT preserve command_palette_enabled - it can work without admin bar (keyboard shortcut)
        if ( ! empty( $clean['hide_admin_bar_in_admin'] ) ) {
            $clean['command_palette_show_admin_bar_icon'] = 0;
        }
        
        // Text fields
        $texts = array( 
            'maintenance_title', 'maintenance_message', 'maintenance_icon',
            'admin_color_scheme'
        );
        foreach ( $texts as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
        }
        
        // Custom footer text - preserve existing value if not in form submission
        if ( isset( $input['custom_footer_text'] ) ) {
            $clean['custom_footer_text'] = sanitize_text_field( $input['custom_footer_text'] );
        } else {
            // Preserve existing value if not in form submission
            $clean['custom_footer_text'] = isset( $existing['custom_footer_text'] ) ? $existing['custom_footer_text'] : '';
        }
        
        // Login button text - preserve existing value if not in form submission
        if ( isset( $input['login_btn_text'] ) ) {
            $clean['login_btn_text'] = sanitize_text_field( $input['login_btn_text'] );
        } else {
            $clean['login_btn_text'] = isset( $existing['login_btn_text'] ) && ! empty( $existing['login_btn_text'] ) ? $existing['login_btn_text'] : 'Log In';
        }
        
        // Custom login URL - preserve existing value if not in form submission
        // This ensures the custom login URL is not lost when saving from other tabs
        if ( isset( $input['custom_login_url'] ) ) {
            $clean['custom_login_url'] = sanitize_text_field( $input['custom_login_url'] );
        } else {
            // Preserve existing value if not in form submission
            $clean['custom_login_url'] = isset( $existing['custom_login_url'] ) ? $existing['custom_login_url'] : '';
        }
        
        // URLs - preserve login and maintenance images if not in form submission
        $urls = array( 'maintenance_bg_image' );
        foreach ( $urls as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( $input[ $key ] ) : '';
        }
        
        // Login logo - preserve existing value if not in form submission
        if ( isset( $input['login_logo'] ) ) {
            $clean['login_logo'] = esc_url_raw( $input['login_logo'] );
        } else {
            $clean['login_logo'] = isset( $existing['login_logo'] ) ? esc_url_raw( $existing['login_logo'] ) : '';
        }
        
        // Login background image - preserve existing value if not in form submission
        if ( isset( $input['login_bg_image'] ) ) {
            $clean['login_bg_image'] = esc_url_raw( $input['login_bg_image'] );
        } else {
            $clean['login_bg_image'] = isset( $existing['login_bg_image'] ) ? esc_url_raw( $existing['login_bg_image'] ) : '';
        }
        
        // Custom admin logo - preserve existing value if not in form submission
        if ( isset( $input['custom_admin_logo'] ) ) {
            $clean['custom_admin_logo'] = esc_url_raw( $input['custom_admin_logo'] );
        } else {
            // Preserve existing value if not in form submission
            $clean['custom_admin_logo'] = isset( $existing['custom_admin_logo'] ) ? esc_url_raw( $existing['custom_admin_logo'] ) : '';
        }
        
        // Colors - preserve login colors if not in form submission
        $colors = array( 
            'maintenance_bg_color', 'maintenance_text_color', 'maintenance_btn_color',
            'admin_accent_color'
        );
        foreach ( $colors as $key ) {
            $clean[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
        }
        
        // Login colors - preserve existing values if not in form submission
        $login_colors_defaults = array( 
            'login_bg_color' => '#f5f5f7',
            'login_form_bg' => '#ffffff',
            'login_btn_color' => '#007aff',
            'login_text_color' => '#1d1d1f',
            'login_bg_overlay' => '#000000'
        );
        foreach ( $login_colors_defaults as $key => $default ) {
            if ( isset( $input[ $key ] ) ) {
                $clean[ $key ] = sanitize_hex_color( $input[ $key ] );
            } else {
                $clean[ $key ] = isset( $existing[ $key ] ) && ! empty( $existing[ $key ] ) ? sanitize_hex_color( $existing[ $key ] ) : $default;
            }
        }
        
        // Background position, size, repeat - preserve existing values if not in form submission
        if ( isset( $input['login_bg_position'] ) ) {
            $clean['login_bg_position'] = sanitize_text_field( $input['login_bg_position'] );
        } else {
            $clean['login_bg_position'] = isset( $existing['login_bg_position'] ) ? sanitize_text_field( $existing['login_bg_position'] ) : 'center center';
        }
        
        if ( isset( $input['login_bg_size'] ) ) {
            $clean['login_bg_size'] = sanitize_text_field( $input['login_bg_size'] );
        } else {
            $clean['login_bg_size'] = isset( $existing['login_bg_size'] ) ? sanitize_text_field( $existing['login_bg_size'] ) : 'cover';
        }
        
        if ( isset( $input['login_bg_repeat'] ) ) {
            $clean['login_bg_repeat'] = sanitize_text_field( $input['login_bg_repeat'] );
        } else {
            $clean['login_bg_repeat'] = isset( $existing['login_bg_repeat'] ) ? sanitize_text_field( $existing['login_bg_repeat'] ) : 'no-repeat';
        }
        
        if ( isset( $input['login_bg_overlay_opacity'] ) ) {
            $clean['login_bg_overlay_opacity'] = absint( $input['login_bg_overlay_opacity'] );
        } else {
            $clean['login_bg_overlay_opacity'] = isset( $existing['login_bg_overlay_opacity'] ) ? absint( $existing['login_bg_overlay_opacity'] ) : 0;
        }
        
        // Numbers
        $clean['max_login_attempts'] = isset( $input['max_login_attempts'] ) ? absint( $input['max_login_attempts'] ) : 5;
        $clean['login_lockout_time'] = isset( $input['login_lockout_time'] ) ? absint( $input['login_lockout_time'] ) : 15;
        $clean['maintenance_blur'] = isset( $input['maintenance_blur'] ) ? absint( $input['maintenance_blur'] ) : 0;
        
        // Login form dimensions - preserve existing values if not in form submission
        if ( isset( $input['login_form_width'] ) ) {
            $clean['login_form_width'] = absint( $input['login_form_width'] );
        } else {
            $clean['login_form_width'] = isset( $existing['login_form_width'] ) && $existing['login_form_width'] !== '' ? absint( $existing['login_form_width'] ) : 320;
        }
        
        if ( isset( $input['login_form_radius'] ) ) {
            $clean['login_form_radius'] = absint( $input['login_form_radius'] );
        } else {
            $clean['login_form_radius'] = isset( $existing['login_form_radius'] ) && $existing['login_form_radius'] !== '' ? absint( $existing['login_form_radius'] ) : 0;
        }
        
        // Login page settings - check if we're in Login tab
        $is_login_tab_check = isset( $input['login_logo'] ) || isset( $input['login_bg_image'] ) || isset( $input['login_bg_color'] ) || isset( $input['login_btn_text'] ) || isset( $input['login_hide_remember'] ) || isset( $input['login_hide_lost_password'] ) || isset( $input['login_hide_back_to_site'] ) || isset( $input['login_hide_register'] ) || isset( $input['login_form_shadow'] ) || isset( $input['login_form_width'] ) || isset( $input['login_custom_message'] );
        
        if ( isset( $input['login_hide_remember'] ) ) {
            $clean['login_hide_remember'] = 1;
        } else {
            // If we're in Login tab and checkbox is not in form, it means it's unchecked (0)
            $clean['login_hide_remember'] = ( $is_login_tab_check ) ? 0 : ( isset( $existing['login_hide_remember'] ) && ! empty( $existing['login_hide_remember'] ) ? 1 : 0 );
        }
        
        if ( isset( $input['login_hide_lost_password'] ) ) {
            $clean['login_hide_lost_password'] = 1;
        } else {
            // If we're in Login tab and checkbox is not in form, it means it's unchecked (0)
            $clean['login_hide_lost_password'] = ( $is_login_tab_check ) ? 0 : ( isset( $existing['login_hide_lost_password'] ) && ! empty( $existing['login_hide_lost_password'] ) ? 1 : 0 );
        }
        
        if ( isset( $input['login_hide_back_to_site'] ) ) {
            $clean['login_hide_back_to_site'] = 1;
        } else {
            // If we're in Login tab and checkbox is not in form, it means it's unchecked (0)
            $clean['login_hide_back_to_site'] = ( $is_login_tab_check ) ? 0 : ( isset( $existing['login_hide_back_to_site'] ) && ! empty( $existing['login_hide_back_to_site'] ) ? 1 : 0 );
        }
        
        if ( isset( $input['login_hide_register'] ) ) {
            $clean['login_hide_register'] = 1;
        } else {
            // If we're in Login tab and checkbox is not in form, it means it's unchecked (0)
            $clean['login_hide_register'] = ( $is_login_tab_check ) ? 0 : ( isset( $existing['login_hide_register'] ) && ! empty( $existing['login_hide_register'] ) ? 1 : 0 );
        }
        
        if ( isset( $input['login_form_shadow'] ) ) {
            $clean['login_form_shadow'] = 1;
        } else {
            // If we're in Login tab and checkbox is not in form, it means it's unchecked (0)
            $clean['login_form_shadow'] = ( $is_login_tab_check ) ? 0 : ( isset( $existing['login_form_shadow'] ) && ! empty( $existing['login_form_shadow'] ) ? 1 : 0 );
        }
        
        // Login custom message - preserve existing value if not in form submission
        if ( isset( $input['login_custom_message'] ) ) {
            $clean['login_custom_message'] = sanitize_textarea_field( $input['login_custom_message'] );
        } else {
            $clean['login_custom_message'] = isset( $existing['login_custom_message'] ) ? sanitize_textarea_field( $existing['login_custom_message'] ) : '';
        }
        
        // Login redirect URL - preserve existing value if not in form submission
        if ( isset( $input['login_redirect_url'] ) ) {
            $clean['login_redirect_url'] = esc_url_raw( $input['login_redirect_url'] );
        } else {
            $clean['login_redirect_url'] = isset( $existing['login_redirect_url'] ) ? esc_url_raw( $existing['login_redirect_url'] ) : '';
        }
        
        // CSS - preserve existing values if not in form submission
        if ( isset( $input['login_custom_css'] ) ) {
            $clean['login_custom_css'] = wp_strip_all_tags( $input['login_custom_css'] );
        } else {
            $clean['login_custom_css'] = isset( $existing['login_custom_css'] ) ? wp_strip_all_tags( $existing['login_custom_css'] ) : '';
        }
        
        if ( isset( $input['custom_admin_css'] ) ) {
            $clean['custom_admin_css'] = wp_strip_all_tags( $input['custom_admin_css'] );
        } else {
            $clean['custom_admin_css'] = isset( $existing['custom_admin_css'] ) ? wp_strip_all_tags( $existing['custom_admin_css'] ) : '';
        }
        
        // Custom dashboard widgets - preserve the list of manually added widgets
        // This ensures custom widgets (like e-dashboard-overview) persist after plugin updates
        // CRITICAL: Save custom_dashboard_widgets to database
        // This MUST be saved so custom widgets always appear in the form
        // IMPORTANT: If form is submitted from Dashboard tab, use submitted values (allows deletion)
        // If form is submitted from other tabs, preserve existing values
        $is_dashboard_tab = isset( $_GET['tab'] ) && $_GET['tab'] === 'dashboard';
        // Also check POST data for tab (in case it's submitted via form)
        if ( ! $is_dashboard_tab && isset( $_POST['tab'] ) && $_POST['tab'] === 'dashboard' ) {
            $is_dashboard_tab = true;
        }
        
        // CRITICAL: Always check if custom_dashboard_widgets is in input, even if it's an empty array
        // An empty array means all widgets were deleted, so we should save it as empty
        if ( array_key_exists( 'custom_dashboard_widgets', $input ) ) {
            // Handle both string (comma-separated) and array formats
            if ( is_string( $input['custom_dashboard_widgets'] ) && ! empty( $input['custom_dashboard_widgets'] ) ) {
                $widget_ids = explode( ',', $input['custom_dashboard_widgets'] );
                $clean['custom_dashboard_widgets'] = array_map( 'sanitize_key', array_filter( array_map( 'trim', $widget_ids ), function( $v ) { return $v !== ''; } ) );
            } elseif ( is_array( $input['custom_dashboard_widgets'] ) ) {
                // CRITICAL: Even if array is empty, we still process it (empty array means all widgets were deleted)
                // Filter out empty strings
                $clean['custom_dashboard_widgets'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['custom_dashboard_widgets'] ), function( $v ) { return $v !== ''; } ) ) );
            } else {
                // Empty string or invalid format - treat as empty array
                $clean['custom_dashboard_widgets'] = array();
            }
        } else {
            // If custom_dashboard_widgets is not in input at all
            if ( $is_dashboard_tab ) {
                // On dashboard tab, if not in input, it means all were deleted
                $clean['custom_dashboard_widgets'] = array();
            } else {
                // On other tabs, preserve existing value from database
                if ( isset( $existing['custom_dashboard_widgets'] ) && is_array( $existing['custom_dashboard_widgets'] ) && ! empty( $existing['custom_dashboard_widgets'] ) ) {
                    $clean['custom_dashboard_widgets'] = array_values( array_unique( array_map( 'sanitize_key', $existing['custom_dashboard_widgets'] ) ) );
                } else {
                    $clean['custom_dashboard_widgets'] = array();
                }
            }
        }
        
        // CRITICAL: If a widget was removed from custom_dashboard_widgets, also remove it from hide_dashboard_widgets
        // This prevents deleted widgets from reappearing in Custom Widgets section
        $existing_custom = isset( $existing['custom_dashboard_widgets'] ) ? $existing['custom_dashboard_widgets'] : array();
        $new_custom = isset( $clean['custom_dashboard_widgets'] ) ? $clean['custom_dashboard_widgets'] : array();
        $deleted_custom = array_diff( $existing_custom, $new_custom );
        
        if ( ! empty( $deleted_custom ) && ! empty( $clean['hide_dashboard_widgets'] ) && is_array( $clean['hide_dashboard_widgets'] ) ) {
            // Remove deleted custom widgets from hide_dashboard_widgets array
            $clean['hide_dashboard_widgets'] = array_values( array_filter( $clean['hide_dashboard_widgets'], function( $widget_id ) use ( $deleted_custom ) {
                return ! in_array( $widget_id, $deleted_custom, true );
            } ) );
        }
        
        // Ensure custom_dashboard_widgets is always an array
        if ( ! isset( $clean['custom_dashboard_widgets'] ) || ! is_array( $clean['custom_dashboard_widgets'] ) ) {
            $clean['custom_dashboard_widgets'] = array();
        }
        
        // CRITICAL: If custom_dashboard_widgets changed, clear the transient cache
        // This ensures widgets are re-categorized correctly after moving to custom widgets
        $existing_custom = isset( $existing['custom_dashboard_widgets'] ) ? $existing['custom_dashboard_widgets'] : array();
        if ( $clean['custom_dashboard_widgets'] !== $existing_custom ) {
            delete_transient( 'wac_dashboard_widgets_list' );
        }
        
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
                    Admin Toolkit
                    <span class="wac-version">v<?php echo WAC_VERSION; ?></span>
                    <?php if ( $is_pro ) : ?><span class="wac-pro-badge">PRO</span><?php endif; ?>
                </h1>
                <p style="margin:8px 0 0;font-size:14px;color:#86868b;max-width:600px">
                    Customize your WordPress admin area, improve productivity, and enhance security with powerful tools.
                </p>
            </div>

            <?php settings_errors( 'wac_settings' ); ?>
            
            <!-- Notification Popup -->
            <div id="wac-notification" class="wac-notification" style="display:none;"></div>

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
                    $url = admin_url( 'admin.php?page=devforge-admin-cleaner&tab=' . $id );
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
                
                <!-- Preserve tab and subtab on form submit -->
                <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
                <?php
                // Determine active subtab for current tab
                $active_sub_tab = '';
                if ( isset( $_GET['subtab'] ) ) {
                    $active_sub_tab = sanitize_key( $_GET['subtab'] );
                } else {
                    // Set default subtab for each tab
                    switch ( $tab ) {
                        case 'dashboard':
                            $active_sub_tab = 'dashboard-widgets';
                            break;
                        case 'appearance':
                            $active_sub_tab = 'appearance-theme';
                            break;
                        case 'menus-roles':
                            $active_sub_tab = 'menus-roles-menu-editor';
                            break;
                        case 'productivity':
                            $active_sub_tab = 'productivity-command-palette';
                            break;
                        case 'cleanup':
                            $active_sub_tab = 'cleanup-database';
                            break;
                        case 'security':
                            $active_sub_tab = 'security-tweaks';
                            break;
                        case 'tools':
                            $active_sub_tab = 'tools-activity-log';
                            break;
                    }
                }
                ?>
                <input type="hidden" name="subtab" value="<?php echo esc_attr( $active_sub_tab ); ?>" id="wac-subtab-input">
                
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

                <?php if ( ! in_array( $tab, array( 'tools' ) ) ) : ?>
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
        .wac-settings-wrap{max-width:900px;margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;color:#1d1d1f;background:transparent!important;padding:32px 20px 20px}
        .wac-settings-wrap *{box-sizing:border-box}
        .wac-settings-wrap h1{font-size:28px;font-weight:600;margin:0 0 8px;display:flex;align-items:center;gap:12px;color:#1d1d1f;letter-spacing:-.5px}
        .wac-settings-wrap h1 .dashicons{display:none}
        .wac-version{font-size:12px;font-weight:400;color:#86868b;margin-left:4px}
        .wac-pro-badge{font-size:10px;font-weight:600;background:#1d1d1f;color:#fff;padding:4px 10px;border-radius:6px;letter-spacing:.3px}
        .wac-pro-badge-small{font-size:9px;font-weight:600;background:#1d1d1f;color:#fff;padding:2px 6px;border-radius:4px;margin-left:6px;letter-spacing:.2px}
        .wac-tabs{display:flex;background:#f5f5f7;border-radius:8px;padding:4px;margin-bottom:24px;gap:6px;border:1px solid #e5e5ea;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none}
        .wac-tabs::-webkit-scrollbar{display:none}
        .wac-tabs .nav-tab{background:transparent;border:none;padding:7px 14px;font-size:12px;font-weight:500;color:#86868b;border-radius:6px;margin:0;cursor:pointer;outline:none;box-shadow:none;transition:all .15s ease;position:relative;white-space:nowrap;flex-shrink:0;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .wac-tabs .nav-tab:focus{outline:none;box-shadow:none}
        .wac-tabs .nav-tab:hover{background:rgba(0,0,0,.04);color:#1d1d1f}
        .wac-tabs .nav-tab-active,.wac-tabs .nav-tab-active:hover{background:#fff;color:#1d1d1f;box-shadow:0 1px 2px rgba(0,0,0,.06);font-weight:600}
        .wac-tabs .nav-tab .wac-pro-badge-small{margin-left:3px;font-size:8px;padding:1px 4px;line-height:1.2}
        .wac-tab-content{background:#fff;border:1px solid #e5e5ea;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}
        
        /* Fixed Checkbox */
        .wac-checkbox-list{display:flex;flex-direction:column;gap:0}
        .wac-checkbox-item{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f5f5f7;cursor:pointer;box-sizing:border-box;width:100%}
        .wac-checkbox-item:last-child{border-bottom:none}
        .wac-checkbox-item:hover{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;width:100%;margin:0;background:#fafafa}
        .wac-checkbox-item span{flex:1;font-size:14px;margin-right:16px;font-weight:400;color:#1d1d1f}
        .wac-checkbox-item input[type=checkbox],.wac-checkbox-item div input[type=checkbox]{-webkit-appearance:none!important;appearance:none!important;width:22px!important;height:22px!important;border:2px solid #d1d1d6!important;border-radius:6px!important;cursor:pointer!important;position:relative!important;flex-shrink:0!important;outline:none!important;box-shadow:none!important;transition:none!important;box-sizing:border-box!important;margin:0!important;padding:0!important;background:#fff!important}
        .wac-checkbox-item input[type=checkbox]::before,.wac-checkbox-item div input[type=checkbox]::before{content:none!important;display:none!important}
        .wac-checkbox-item input[type=checkbox]:hover,.wac-checkbox-item div input[type=checkbox]:hover{border-color:#007aff!important;width:22px!important;height:22px!important}
        .wac-checkbox-item input[type=checkbox]:focus,.wac-checkbox-item div input[type=checkbox]:focus{outline:none!important;box-shadow:none!important;border-color:#007aff!important}
        .wac-checkbox-item input[type=checkbox]:checked,.wac-checkbox-item div input[type=checkbox]:checked{background:#007aff!important;border-color:#007aff!important}
        .wac-checkbox-item input[type=checkbox]:checked::before,.wac-checkbox-item div input[type=checkbox]:checked::before{content:none!important;display:none!important}
        .wac-checkbox-item input[type=checkbox]:checked::after,.wac-checkbox-item div input[type=checkbox]:checked::after{content:''!important;position:absolute!important;width:6px!important;height:10px!important;border:solid #fff!important;border-width:0 2px 2px 0!important;top:calc(50% - 2.5px)!important;left:calc(50% - 0.5px)!important;transform:translate(-50%,-50%) rotate(45deg)!important;transform-origin:center!important;display:block!important}
        
        /* Global Checkbox Style - Apply to all checkboxes in plugin (except wac-switch) */
        .wac-tab-content input[type=checkbox],.wac-settings-section input[type=checkbox],.wac-role-grid input[type=checkbox]{-webkit-appearance:none!important;appearance:none!important;width:22px!important;height:22px!important;border:2px solid #d1d1d6!important;border-radius:6px!important;cursor:pointer!important;position:relative!important;flex-shrink:0!important;outline:none!important;box-shadow:none!important;transition:none!important;box-sizing:border-box!important;margin:0!important;padding:0!important;background:#fff!important}
        .wac-tab-content input[type=checkbox]::before,.wac-settings-section input[type=checkbox]::before,.wac-role-grid input[type=checkbox]::before{content:none!important;display:none!important}
        .wac-tab-content input[type=checkbox]:hover,.wac-settings-section input[type=checkbox]:hover,.wac-role-grid input[type=checkbox]:hover{border-color:#007aff!important}
        .wac-tab-content input[type=checkbox]:focus,.wac-settings-section input[type=checkbox]:focus,.wac-role-grid input[type=checkbox]:focus{outline:none!important;box-shadow:none!important;border-color:#007aff!important}
        .wac-tab-content input[type=checkbox]:checked,.wac-settings-section input[type=checkbox]:checked,.wac-role-grid input[type=checkbox]:checked{background:#007aff!important;border-color:#007aff!important}
        .wac-tab-content input[type=checkbox]:checked::before,.wac-settings-section input[type=checkbox]:checked::before,.wac-role-grid input[type=checkbox]:checked::before{content:none!important;display:none!important}
        .wac-tab-content input[type=checkbox]:checked::after,.wac-settings-section input[type=checkbox]:checked::after,.wac-role-grid input[type=checkbox]:checked::after{content:''!important;position:absolute!important;width:6px!important;height:10px!important;border:solid #fff!important;border-width:0 2px 2px 0!important;top:calc(50% - 2.5px)!important;left:calc(50% - 0.5px)!important;transform:translate(-50%,-50%) rotate(45deg)!important;transform-origin:center!important;display:block!important}
        
        /* Exclude wac-switch checkboxes - they use toggle switch design */
        .wac-switch input[type=checkbox]{-webkit-appearance:auto!important;appearance:auto!important;width:auto!important;height:auto!important;border:none!important;border-radius:0!important;background:transparent!important;opacity:0!important}
        .wac-switch input[type=checkbox]::before,.wac-switch input[type=checkbox]::after{content:none!important;display:none!important}
        
        .wac-role-block{margin-bottom:24px;padding:16px;background:#f9f9fb;border-radius:8px}
        .wac-role-block:last-child{margin-bottom:0}
        .wac-role-header{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#86868b;margin-bottom:12px}
        .wac-role-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
        .wac-role-grid label{display:flex;align-items:center;gap:8px;font-size:13px;padding:10px 12px;background:#fff;border:1px solid #e5e5ea;border-radius:8px;cursor:pointer;line-height:1.4}
        .wac-role-grid label:hover{background:#f5f5f7;border-color:#d1d1d6}
        .wac-row{display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid #f5f5f7;transition:background .15s ease}
        .wac-row:last-child{border-bottom:none}
        .wac-row:hover{background:#fafafa}
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
        .wac-settings-form .button-primary,.wac-settings-wrap .button-primary,.wac-settings-form button.button-primary,.wac-settings-form #submit.button-primary{background:#000 !important;border:1px solid #000 !important;border-radius:6px;padding:8px 20px;font-size:13px;font-weight:500;height:auto;line-height:1.4;box-shadow:none !important;color:#fff !important}
        .wac-settings-form .button-primary:hover,.wac-settings-wrap .button-primary:hover,.wac-settings-form .button-primary:active,.wac-settings-wrap .button-primary:active,.wac-settings-form .button-primary:focus,.wac-settings-wrap .button-primary:focus,.wac-settings-form button.button-primary:hover,.wac-settings-form #submit.button-primary:hover{background:#000 !important;color:#fff !important;border-color:#000 !important;box-shadow:none !important;outline:none}
        
        /* Notification Popup - Center of screen */
        .wac-notification{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:999999;max-width:400px;min-width:280px;padding:20px 28px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.3);font-size:14px;font-weight:500;text-align:center;color:#fff;animation:wac-popup-in .3s ease-out}
        .wac-notification.success{background:#34c759}
        .wac-notification.error{background:#ff3b30}
        .wac-notification.info{background:#007aff}
        .wac-notification.warning{background:#ff9500}
        @keyframes wac-popup-in{from{opacity:0;transform:translate(-50%,-50%) scale(0.9)}to{opacity:1;transform:translate(-50%,-50%) scale(1)}}
        .wac-notification-close{position:absolute;top:8px;right:12px;cursor:pointer;opacity:0.7;font-size:20px;line-height:1;transition:opacity 0.2s}
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
        .wac-btn,.wac-settings-wrap .wac-btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s ease;text-decoration:none;line-height:1.4;min-height:36px;box-shadow:none;outline:none}
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
        .wac-unlock-btn:hover{background:#000;color:#fff}
        
        /* Feature List */
        .wac-feature-list{display:flex;flex-direction:column;gap:8px;padding:16px;background:#f9f9fb;border-radius:8px;margin-top:8px}
        .wac-feature-item{display:flex;align-items:center;gap:10px;font-size:13px;color:#1d1d1f;padding:4px 0}
        .wac-feature-item .dashicons{color:#34c759;font-size:16px;width:16px;height:16px}
        
        /* Feature Preview */
        .wac-feature-preview{background:#f9f9fb;border-radius:8px;padding:40px;text-align:center;margin-top:8px}
        .wac-preview-placeholder{color:#86868b;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center}
        .wac-preview-placeholder .dashicons{display:block;margin-bottom:20px}
        .wac-preview-placeholder p{margin:0;font-size:13px}
        
        /* Kbd */
        kbd{background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px;font-family:inherit;font-weight:500}
        
        /* Improved Section Spacing */
        .wac-settings-section{padding:24px 28px;border-bottom:1px solid #e5e5ea;background:transparent!important}
        .wac-settings-section:last-child{border-bottom:none}
        .wac-settings-section h2{font-size:16px;font-weight:600;text-transform:none;letter-spacing:0;color:#1d1d1f;margin:0 0 6px;line-height:1.3}
        .wac-settings-section>p{margin:0 0 20px;line-height:1.5}
        
        
        /* Improved Content Area */
        .wac-tab-content{background:#fff;border:1px solid #e5e5ea;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}
        
        /* Better Form Elements */
        .wac-settings-section input[type=text],.wac-settings-section input[type=url],.wac-settings-section input[type=number],.wac-settings-section select,.wac-settings-section textarea{width:100%;max-width:400px;font-family:inherit;font-size:14px;padding:10px 14px;border:1px solid #d1d1d6;border-radius:8px;background:#fff;transition:all .15s ease}
        .wac-settings-section input:focus,.wac-settings-section textarea:focus,.wac-settings-section select:focus{outline:none;border-color:#007aff;box-shadow:0 0 0 3px rgba(0,122,255,.1)}
        
        /* Better Submit Area - Match top border-radius and add margin */
        .wac-settings-form .submit,
        .wac-settings-wrap .wac-settings-form .submit,
        form.wac-settings-form .submit{
            padding:20px 24px !important;
            margin:30px 0 0 0 !important;
            background:#f9f9fb !important;
            border:1px solid #e5e5ea !important;
            border-top:1px solid #e5e5ea !important;
            border-radius:10px !important;
        }
        .wac-settings-form .submit p,
        .wac-settings-wrap .wac-settings-form .submit p{
            margin:0 !important;
        }
        
        /* Code styling */
        code{background:#f5f5f7;padding:2px 6px;border-radius:4px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#1d1d1f}
        
        /* Sub-tabs (Inner tabs) - Minimal design, different from main tabs */
        .wac-sub-tabs{display:flex;background:transparent;border-bottom:1px solid #e5e5ea;padding:0;margin-bottom:20px;gap:0;flex-wrap:wrap}
        .wac-sub-tabs .wac-sub-tab{background:transparent;border:none;border-bottom:2px solid transparent;padding:10px 16px;font-size:12px;font-weight:500;color:#86868b;border-radius:0;margin:0;cursor:pointer;outline:none;box-shadow:none;transition:all .15s ease;position:relative;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .wac-sub-tabs .wac-sub-tab:focus{outline:none;box-shadow:none}
        .wac-sub-tabs .wac-sub-tab:hover{color:#1d1d1f;background:transparent}
        .wac-sub-tabs .wac-sub-tab-active,.wac-sub-tabs .wac-sub-tab-active:hover{background:transparent;color:#007aff;border-bottom-color:#007aff;font-weight:600;box-shadow:none}
        
        /* Sub-tab content */
        .wac-sub-tab-content{display:none}
        .wac-sub-tab-content.active{display:block}
        
        /* Consistent section styling */
        .wac-settings-section{background:transparent!important;padding:24px 28px;border-bottom:1px solid #e5e5ea;margin:0}
        .wac-settings-section:last-child{border-bottom:none}
        
        /* Section spacing - when no sub-tabs (directly in tab-content) */
        .wac-tab-content > .wac-settings-section:first-child{border-top-left-radius:10px;border-top-right-radius:10px}
        .wac-tab-content > .wac-settings-section:last-child{border-bottom-left-radius:10px;border-bottom-right-radius:10px}
        .wac-tab-content > .wac-settings-section:only-child{border-radius:10px}
        
        /* Section spacing within sub-tabs */
        .wac-sub-tab-content .wac-settings-section:first-child{border-top-left-radius:10px;border-top-right-radius:10px;margin-top:0}
        .wac-sub-tab-content .wac-settings-section:last-child{border-bottom-left-radius:10px;border-bottom-right-radius:10px;margin-bottom:0}
        .wac-sub-tab-content .wac-settings-section:only-child{border-radius:10px}
        
        /* When sub-tabs exist, hide direct sections (they should be in sub-tab-content) */
        .wac-tab-content .wac-sub-tabs ~ .wac-settings-section{display:none}
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
            
            // Form submit - don't show notification here, let page reload handle it
            // Notification will be shown after page reload via URL parameter
            
            // Media Upload (general handler - skip if has data-target, handled by Maintenance Mode specific handler)
            $('.wac-media-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                // Skip buttons with data-target (handled by Maintenance Mode specific handler)
                if (btn.data('target')) {
                    return;
                }
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
                    frame.close();
                    if (typeof wacShowNotification === 'function') {
                        wacShowNotification('Image selected', 'success');
                    }
                });
                
                frame.open();
            });
            
            // Sub-tabs functionality
            $('.wac-sub-tab').on('click', function(e) {
                e.preventDefault();
                var $tab = $(this);
                var target = $tab.data('target');
                
                // Remove active class from all tabs and contents
                $tab.closest('.wac-sub-tabs').find('.wac-sub-tab').removeClass('wac-sub-tab-active');
                $tab.closest('.wac-tab-content').find('.wac-sub-tab-content').removeClass('active');
                
                // Add active class to clicked tab and corresponding content
                $tab.addClass('wac-sub-tab-active');
                $('#' + target).addClass('active');
                
                // Update hidden input for subtab
                var $subtabInput = $('#wac-subtab-input');
                if ($subtabInput.length) {
                    $subtabInput.val(target);
                }
                
                // Update URL hash without page reload
                if (history.pushState) {
                    history.pushState(null, null, '#' + target);
                }
            });
            
            // Handle hash on page load - CRITICAL: Wait for DOM to be fully ready
            function activateSubTabFromHash() {
                var subtabToActivate = null;
                
                // First check URL hash
                if (window.location.hash) {
                    subtabToActivate = window.location.hash.substring(1);
                } 
                // Fallback: Check URL parameter if hash is not available
                else if (window.location.search.indexOf('subtab=') !== -1) {
                    var urlParams = new URLSearchParams(window.location.search);
                    subtabToActivate = urlParams.get('subtab');
                    // Also update hash if we found subtab in URL parameter
                    if (subtabToActivate && history.pushState) {
                        history.pushState(null, null, window.location.pathname + window.location.search + '#' + subtabToActivate);
                    }
                }
                
                if (subtabToActivate) {
                    // Wait a bit for elements to be ready, then try to activate
                    var attempts = 0;
                    var maxAttempts = 10;
                    
                    function tryActivate() {
                        attempts++;
                        var $targetTab = $('.wac-sub-tab[data-target="' + subtabToActivate + '"]');
                        var $targetContent = $('#' + subtabToActivate);
                        
                        if ($targetTab.length && $targetContent.length) {
                            // Remove active class from all tabs first
                            $('.wac-sub-tab').removeClass('wac-sub-tab-active');
                            $('.wac-sub-tab-content').removeClass('active');
                            
                            // Activate the target tab
                            $targetTab.addClass('wac-sub-tab-active');
                            $targetContent.addClass('active');
                            
                            // Update hidden input
                            var $subtabInput = $('#wac-subtab-input');
                            if ($subtabInput.length) {
                                $subtabInput.val(subtabToActivate);
                            }
                            
                            return true; // Success
                        } else if (attempts < maxAttempts) {
                            // Elements not ready yet, try again
                            setTimeout(tryActivate, 100);
                            return false;
                        }
                        return false;
                    }
                    
                    tryActivate();
                }
            }
            
            // Try multiple times with increasing delays
            activateSubTabFromHash();
            setTimeout(activateSubTabFromHash, 50);
            setTimeout(activateSubTabFromHash, 100);
            setTimeout(activateSubTabFromHash, 200);
            setTimeout(activateSubTabFromHash, 500);
            setTimeout(activateSubTabFromHash, 1000);
            
        });
        
        // CRITICAL: Also try on window load (after all resources are loaded)
        window.addEventListener('load', function() {
            setTimeout(function() {
                var subtabToActivate = null;
                
                // First check URL hash
                if (window.location.hash) {
                    subtabToActivate = window.location.hash.substring(1);
                } 
                // Fallback: Check URL parameter if hash is not available
                else if (window.location.search.indexOf('subtab=') !== -1) {
                    var urlParams = new URLSearchParams(window.location.search);
                    subtabToActivate = urlParams.get('subtab');
                    // Also update hash if we found subtab in URL parameter
                    if (subtabToActivate && history.pushState) {
                        history.pushState(null, null, window.location.pathname + window.location.search + '#' + subtabToActivate);
                    }
                }
                
                if (subtabToActivate && typeof jQuery !== 'undefined') {
                    jQuery(function($) {
                        var $targetTab = $('.wac-sub-tab[data-target="' + subtabToActivate + '"]');
                        var $targetContent = $('#' + subtabToActivate);
                        
                        if ($targetTab.length && $targetContent.length) {
                            // Remove active class from all tabs first
                            $('.wac-sub-tab').removeClass('wac-sub-tab-active');
                            $('.wac-sub-tab-content').removeClass('active');
                            
                            // Activate the target tab
                            $targetTab.addClass('wac-sub-tab-active');
                            $targetContent.addClass('active');
                            
                            // Update hidden input
                            var $subtabInput = $('#wac-subtab-input');
                            if ($subtabInput.length) {
                                $subtabInput.val(subtabToActivate);
                            }
                            
                            // Scroll to top of subtab content if needed
                            $targetContent[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                }
            }, 100);
        });
        </script>
        <?php
    }

    /**
     * Render sub-tabs navigation
     */
    private function render_sub_tabs( $sub_tabs, $default_tab = '' ) {
        if ( empty( $sub_tabs ) ) return '';
        
        $default_tab = $default_tab ?: array_key_first( $sub_tabs );
        ?>
        <nav class="wac-sub-tabs">
            <?php foreach ( $sub_tabs as $id => $name ) : 
                $class = ( $id === $default_tab ) ? 'wac-sub-tab wac-sub-tab-active' : 'wac-sub-tab';
            ?>
                <a href="#<?php echo esc_attr( $id ); ?>" class="<?php echo $class; ?>" data-target="<?php echo esc_attr( $id ); ?>">
                    <?php echo esc_html( $name ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private function tab_dashboard( $opt, $is_pro ) {
        $widgets = isset( $opt['hide_dashboard_widgets'] ) ? $opt['hide_dashboard_widgets'] : array();
        $bar = isset( $opt['hide_admin_bar_items'] ) ? $opt['hide_admin_bar_items'] : array();
        
        // Sub-tabs for Dashboard
        $sub_tabs = array(
            'dashboard-widgets' => 'Widgets',
            'dashboard-toolbar' => 'Toolbar',
            'dashboard-general' => 'General',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'dashboard-widgets';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'dashboard-widgets';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Dashboard Widgets Sub-tab -->
        <div id="dashboard-widgets" class="wac-sub-tab-content <?php echo $active_sub_tab === 'dashboard-widgets' ? 'active' : ''; ?>">
        
        <!-- Dashboard Widget Builder -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Custom Widgets</h2>
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
                <h2>Announcements</h2>
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
                        <br><small style="color:#ff9500">💡 Tip: Visit the Dashboard page first to load all widgets, then return here to see them.</small>
                    </p>
                </div>
            </div>
            <div class="wac-checkbox-list">
                <?php
                // CRITICAL: Get custom widgets from database FIRST - they MUST always appear in form
                // This is done BEFORE getting detected widgets to ensure they're never lost
                $custom_widgets_db = isset( $opt['custom_dashboard_widgets'] ) ? $opt['custom_dashboard_widgets'] : array();
                $custom_widgets_list = array();
                
                // CRITICAL: Normalize $widgets array for reliable comparison (case-insensitive, trimmed)
                // This ensures checkbox states are correct
                $widgets_normalized = array();
                if ( ! empty( $widgets ) && is_array( $widgets ) ) {
                    foreach ( $widgets as $w ) {
                        $widgets_normalized[] = strtolower( trim( $w ) );
                    }
                }
                
                // Add custom widgets directly from database FIRST
                // This ensures they ALWAYS appear in the form, regardless of cache or detection
                if ( ! empty( $custom_widgets_db ) && is_array( $custom_widgets_db ) ) {
                    foreach ( $custom_widgets_db as $custom_id ) {
                        if ( ! empty( $custom_id ) ) {
                            $title = ucwords( str_replace( array( '_', '-' ), ' ', $custom_id ) );
                            $custom_widgets_list[ $custom_id ] = $title;
                        }
                    }
                }
                
                // Get all registered widgets dynamically (after custom widgets are set)
                $all_widgets = WAC_Dashboard_Widgets::get_all_widgets();
                
                // CRITICAL: Remove all custom widgets from $all_widgets BEFORE categorization
                // This ensures custom widgets never appear in plugin/theme/core sections
                // Normalize custom widget IDs for case-insensitive comparison
                $custom_ids_normalized = array();
                if ( ! empty( $custom_widgets_db ) && is_array( $custom_widgets_db ) ) {
                    foreach ( $custom_widgets_db as $custom_id ) {
                        $custom_ids_normalized[ strtolower( trim( $custom_id ) ) ] = $custom_id;
                    }
                }
                if ( ! empty( $custom_widgets_list ) && is_array( $custom_widgets_list ) ) {
                    foreach ( $custom_widgets_list as $custom_id => $custom_title ) {
                        $custom_ids_normalized[ strtolower( trim( $custom_id ) ) ] = $custom_id;
                    }
                }
                
                // Also check Dashboard Builder custom widgets (wac_custom_widgets option)
                // These widgets have IDs like "wac_widget_w_1767123815676" and should also be excluded
                // Dashboard Builder adds "wac_widget_" prefix when registering widgets
                if ( $is_pro && class_exists( 'WAC_Dashboard_Builder' ) ) {
                    $dashboard_builder_widgets = get_option( 'wac_custom_widgets', array() );
                    if ( ! empty( $dashboard_builder_widgets ) && is_array( $dashboard_builder_widgets ) ) {
                        foreach ( $dashboard_builder_widgets as $builder_widget_id => $builder_widget_data ) {
                            // Widget ID might be the key or might be in widget data
                            $builder_id = is_string( $builder_widget_id ) ? $builder_widget_id : ( isset( $builder_widget_data['id'] ) ? $builder_widget_data['id'] : '' );
                            if ( ! empty( $builder_id ) ) {
                                // Add both the original ID and the prefixed version (wac_widget_ + ID)
                                $custom_ids_normalized[ strtolower( trim( $builder_id ) ) ] = $builder_id;
                                $prefixed_id = 'wac_widget_' . $builder_id;
                                $custom_ids_normalized[ strtolower( trim( $prefixed_id ) ) ] = $prefixed_id;
                            }
                        }
                    }
                }
                
                // Remove custom widgets from $all_widgets (case-insensitive)
                foreach ( $all_widgets as $widget_id => $widget_data ) {
                    $widget_id_normalized = strtolower( trim( $widget_id ) );
                    if ( isset( $custom_ids_normalized[ $widget_id_normalized ] ) ) {
                        unset( $all_widgets[ $widget_id ] );
                    }
                }
                
                // CRITICAL: Remove wac_announcements widget from list
                // Announcements widget has its own enabled/disabled control, so it shouldn't appear in Hide Dashboard Widgets
                if ( isset( $all_widgets['wac_announcements'] ) ) {
                    unset( $all_widgets['wac_announcements'] );
                }
                
                // Group widgets by source
                $core_widgets = array();
                $plugin_widgets = array();
                $theme_widgets = array();
                
                // THEN: Process detected widgets from $all_widgets (custom widgets already removed)
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
                
                // Display plugin widgets (exclude custom widgets)
                if ( ! empty( $plugin_widgets ) ) {
                    $filtered_plugin_widgets = array();
                    foreach ( $plugin_widgets as $id => $label ) {
                        // Skip if this is a custom widget (case-insensitive check)
                        $id_normalized = strtolower( trim( $id ) );
                        if ( isset( $custom_ids_normalized[ $id_normalized ] ) ) {
                            continue;
                        }
                        $filtered_plugin_widgets[ $id ] = $label;
                    }
                    if ( ! empty( $filtered_plugin_widgets ) ) {
                        echo '<div style="margin:24px 0 16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">Plugins & Themes</strong></div>';
                        foreach ( $filtered_plugin_widgets as $id => $label ) : 
                            // Check if this widget should be treated as custom (user wants to delete it)
                            // If it's in custom_dashboard_widgets, show delete button
                            $is_custom_for_deletion = ! empty( $custom_widgets_db ) && in_array( $id, $custom_widgets_db );
                            ?>
                            <label class="wac-checkbox-item" data-widget-id="<?php echo esc_attr( $id ); ?>">
                                <span><?php echo esc_html( $label ); ?> <small style="color:#86868b;font-size:11px">(<?php echo esc_html( $id ); ?>)</small></span>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" 
                                           value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $widgets ) ); ?>>
                                    <button type="button" class="wac-move-to-custom-widget" data-widget-id="<?php echo esc_attr( $id ); ?>" 
                                            style="background:none;border:none;color:#ff3b30;cursor:pointer;padding:4px;font-size:16px;line-height:1;opacity:0.7;transition:opacity 0.2s" 
                                            title="Move to Custom Widgets and delete">
                                        ×
                                    </button>
                                </div>
                            </label>
                        <?php endforeach;
                    }
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
                
                // Display custom widgets in main list (CRITICAL: They MUST always appear here with checkboxes)
                // ALWAYS show custom widgets from database, regardless of $custom_widgets_list state
                // This ensures hidden custom widgets are always visible in the form
                // CRITICAL: Even if $custom_widgets_db is empty, we should still check for widgets that are hidden
                // because they might be custom widgets that were hidden but not yet saved to custom_dashboard_widgets
                $has_custom_section = false;
                if ( ! empty( $custom_widgets_db ) && is_array( $custom_widgets_db ) ) {
                    echo '<div style="margin:24px 0 16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">Custom Widgets</strong></div>';
                    $has_custom_section = true;
                    foreach ( $custom_widgets_db as $custom_id ) {
                        if ( ! empty( $custom_id ) ) {
                            // Use label from $custom_widgets_list if available, otherwise generate from ID
                            $label = isset( $custom_widgets_list[ $custom_id ] ) ? $custom_widgets_list[ $custom_id ] : ucwords( str_replace( array( '_', '-' ), ' ', $custom_id ) );
                            // Check if this widget is currently hidden - use normalized comparison for reliability
                            $custom_id_normalized = strtolower( trim( $custom_id ) );
                            $is_hidden = in_array( $custom_id_normalized, $widgets_normalized, true );
                            ?>
                            <label class="wac-checkbox-item" data-custom-widget-id="<?php echo esc_attr( $custom_id ); ?>">
                                <span><?php echo esc_html( $label ); ?> <small style="color:#86868b;font-size:11px">(<?php echo esc_html( $custom_id ); ?>)</small></span>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" 
                                           value="<?php echo esc_attr( $custom_id ); ?>" <?php checked( $is_hidden, true ); ?>>
                                    <button type="button" class="wac-delete-custom-widget" data-widget-id="<?php echo esc_attr( $custom_id ); ?>" 
                                            style="background:none;border:none;color:#ff3b30;cursor:pointer;padding:4px;font-size:16px;line-height:1;opacity:0.7;transition:opacity 0.2s" 
                                            title="Delete this custom widget">
                                        ×
                                    </button>
                                </div>
                                <!-- CRITICAL: Hidden input to preserve custom widget in database - MUST be present -->
                                <input type="hidden" name="wac_settings[custom_dashboard_widgets][]" value="<?php echo esc_attr( $custom_id ); ?>">
                            </label>
                            <?php
                        }
                    }
                }
                
                // REMOVED: Auto-adding hidden widgets to Custom Widgets section
                // This was causing deleted widgets to reappear
                // Only widgets explicitly added via "Add Custom Widget ID" or moved from Plugins & Themes should appear here
                // If a widget is in hide_dashboard_widgets but NOT in custom_dashboard_widgets, it should NOT appear in Custom Widgets section
                
                // If no widgets found
                if ( empty( $core_widgets ) && empty( $plugin_widgets ) && empty( $theme_widgets ) && empty( $custom_widgets_db ) ) {
                    echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;margin:16px 0">';
                    echo '<p style="margin:0 0 8px;font-size:13px;color:#856404;font-weight:500">No widgets detected yet</p>';
                    echo '<p style="margin:0;font-size:12px;color:#856404">To see all dashboard widgets (including Elementor, WooCommerce, etc.):</p>';
                    echo '<ol style="margin:8px 0 0 20px;padding:0;font-size:12px;color:#856404">';
                    echo '<li>Visit the <a href="' . admin_url() . '" target="_blank" style="color:#007aff">Dashboard page</a></li>';
                    echo '<li>Return to this page - widgets will appear automatically</li>';
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
                    // This section is for adding NEW custom widgets only
                    // Existing custom widgets are shown in the main list above
                    ?>
                </div>
            </div>
            
            <script>
            jQuery(function($) {
                // Scan Dashboard and Refresh List buttons removed - they were causing custom widgets to disappear
                // Custom widgets are now always loaded from database and their hidden state is preserved
                
                // Add custom widget
                $('#wac-add-custom-widget').on('click', function() {
                    var widgetId = $('#wac-custom-widget-id').val().trim();
                    if (widgetId) {
                        // Check if already exists in main list
                        var exists = false;
                        $('.wac-checkbox-list input[value="' + widgetId + '"]').each(function() {
                            exists = true;
                        });
                        
                        if (!exists) {
                            // Add to main widget list (Custom Widgets section)
                            var $customSection = $('.wac-checkbox-list').find('strong:contains("Custom Widgets")').closest('div');
                            if ($customSection.length === 0) {
                                // Create Custom Widgets section if it doesn't exist
                                $customSection = $('<div style="margin:24px 0 16px"><strong style="font-size:12px;color:#86868b;text-transform:uppercase;letter-spacing:.5px">Custom Widgets</strong></div>');
                                $('.wac-checkbox-list').append($customSection);
                            }
                            
                            var title = widgetId.replace(/[-_]/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                            var $newLabel = $('<label class="wac-checkbox-item" data-custom-widget-id="' + $('<div>').text(widgetId).html() + '">');
                            $newLabel.html('<span>' + $('<div>').text(title).html() + ' <small style="color:#86868b;font-size:11px">(' + $('<div>').text(widgetId).html() + ')</small></span>' +
                                '<div style="display:flex;align-items:center;gap:8px">' +
                                '<input type="checkbox" name="wac_settings[hide_dashboard_widgets][]" value="' + $('<div>').text(widgetId).html() + '">' +
                                '<button type="button" class="wac-delete-custom-widget" data-widget-id="' + $('<div>').text(widgetId).html() + '" ' +
                                'style="background:none;border:none;color:#ff3b30;cursor:pointer;padding:4px;font-size:16px;line-height:1;opacity:0.7;transition:opacity 0.2s" ' +
                                'title="Delete this custom widget">×</button>' +
                                '</div>' +
                                '<input type="hidden" name="wac_settings[custom_dashboard_widgets][]" value="' + $('<div>').text(widgetId).html() + '">');
                            $customSection.after($newLabel);
                            $('#wac-custom-widget-id').val('');
                        } else {
                            alert('This widget ID is already added.');
                        }
                    }
                });
                
                // Function to update hidden inputs with all custom widget IDs
                // NOTE: PHP already adds hidden inputs for existing custom widgets, this only handles NEW ones added via JS
                function updateCustomWidgetsInput() {
                    // Get all custom widget IDs from Custom Widgets section
                    var customWidgetIds = [];
                    
                    // Find Custom Widgets section in main list
                    var $customSection = $('.wac-checkbox-list').find('strong').filter(function() {
                        return $(this).text().trim() === 'Custom Widgets';
                    }).closest('div');
                    
                    if ($customSection.length) {
                        // Get all widgets after Custom Widgets header
                        $customSection.nextAll('label.wac-checkbox-item[data-custom-widget-id]').each(function() {
                            var $checkbox = $(this).find('input[type="checkbox"][name="wac_settings[hide_dashboard_widgets][]"]');
                            if ($checkbox.length) {
                                var widgetId = $checkbox.val();
                                if (widgetId) {
                                    customWidgetIds.push(widgetId);
                                }
                            }
                        });
                    }
                    
                    // CRITICAL: Remove ALL existing hidden inputs first
                    $('input[type="hidden"][name="wac_settings[custom_dashboard_widgets][]"]').remove();
                    
                    // CRITICAL: Re-create hidden inputs for all remaining widgets
                    // This ensures the array is always sent, even if empty
                    // We'll add them to a container at the end of the form
                    var $form = $('#wac-settings-form');
                    var $container = $('#wac-custom-widgets-hidden-container');
                    if ($container.length === 0) {
                        $container = $('<div id="wac-custom-widgets-hidden-container" style="display:none"></div>');
                        $form.append($container);
                    }
                    $container.empty();
                    
                    // Add hidden inputs for each remaining widget
                    customWidgetIds.forEach(function(widgetId) {
                        $container.append($('<input>', {
                            type: 'hidden',
                            name: 'wac_settings[custom_dashboard_widgets][]',
                            value: widgetId
                        }));
                    });
                    
                    // CRITICAL: If no widgets remain, add an empty hidden input to ensure array is sent as empty
                    if (customWidgetIds.length === 0) {
                        $container.append($('<input>', {
                            type: 'hidden',
                            name: 'wac_settings[custom_dashboard_widgets][]',
                            value: ''
                        }));
                    }
                    
                    // CRITICAL: Also remove checkboxes for deleted custom widgets from hide_dashboard_widgets
                    var allHiddenCheckboxes = $('input[type="checkbox"][name="wac_settings[hide_dashboard_widgets][]"]');
                    allHiddenCheckboxes.each(function() {
                        var widgetId = $(this).val();
                        var $label = $(this).closest('label.wac-checkbox-item[data-custom-widget-id]');
                        if ($label.length && customWidgetIds.indexOf(widgetId) === -1) {
                            // This is a deleted custom widget - uncheck and remove it
                            $(this).prop('checked', false).remove();
                        }
                    });
                }
                
                // Update custom widgets input on form submit (BEFORE form is submitted)
                $('#wac-settings-form').on('submit', function(e) {
                    // Update before submit to ensure deleted widgets are removed
                    updateCustomWidgetsInput();
                    
                    // CRITICAL: Update subtab hidden input from current active subtab
                    var $activeSubTab = $('.wac-sub-tab-active');
                    if ($activeSubTab.length) {
                        var activeSubTabTarget = $activeSubTab.data('target');
                        var $subtabInput = $('#wac-subtab-input');
                        if ($subtabInput.length && activeSubTabTarget) {
                            $subtabInput.val(activeSubTabTarget);
                        }
                    }
                    
                    // Allow form to submit normally - redirect will be handled by PHP
                    return true;
                });
                
                // Initialize on page load
                updateCustomWidgetsInput();
                
                // Delete custom widget
                $(document).on('click', '.wac-delete-custom-widget', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $btn = $(this);
                    var widgetId = $btn.data('widget-id');
                    var $label = $btn.closest('label.wac-checkbox-item');
                    
                    if (confirm('Are you sure you want to remove "' + widgetId + '" from custom widgets?')) {
                        // CRITICAL: Remove hidden input FIRST before removing the label
                        // This ensures the widget ID is not sent in form submission
                        $label.find('input[type="hidden"][name="wac_settings[custom_dashboard_widgets][]"]').remove();
                        
                        // CRITICAL: Also uncheck and REMOVE the checkbox from DOM to prevent it from being sent
                        // This prevents the widget from being re-added to Custom Widgets section
                        var $checkbox = $label.find('input[type="checkbox"][name="wac_settings[hide_dashboard_widgets][]"]');
                        if ($checkbox.length) {
                            $checkbox.prop('checked', false);
                            // Remove the checkbox from DOM so it's not sent in form submission
                            $checkbox.remove();
                        }
                        
                        // Update custom widgets input BEFORE removing the label
                        // This ensures the widget is removed from all hidden inputs
                        updateCustomWidgetsInput();
                        
                        // Remove the label (which contains checkbox and delete button)
                        $label.fadeOut(200, function() {
                            $(this).remove();
                            
                            // CRITICAL: Automatically submit the form to save the deletion immediately
                            // This ensures the widget is removed from the database right away
                            // Add a small delay to ensure DOM is updated
                            setTimeout(function() {
                                $('#wac-settings-form').submit();
                            }, 100);
                        });
                    }
                });
                
                // Move widget from Plugins & Themes to Custom Widgets and mark for deletion
                $(document).on('click', '.wac-move-to-custom-widget', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $btn = $(this);
                    var widgetId = $btn.data('widget-id');
                    var $label = $btn.closest('label.wac-checkbox-item');
                    var $checkbox = $label.find('input[type="checkbox"][name="wac_settings[hide_dashboard_widgets][]"]');
                    
                    if (confirm('Move "' + widgetId + '" to Custom Widgets and delete it?')) {
                        // Add hidden input to mark this widget as custom
                        $label.append('<input type="hidden" name="wac_settings[custom_dashboard_widgets][]" value="' + $('<div>').text(widgetId).html() + '">');
                        
                        // Also check the checkbox to hide the widget
                        if ($checkbox.length && !$checkbox.is(':checked')) {
                            $checkbox.prop('checked', true);
                        }
                        
                        // Update custom widgets input to ensure it's included in form submission
                        updateCustomWidgetsInput();
                        
                        // Remove from Plugins & Themes section
                        $label.fadeOut(200, function() {
                            $(this).remove();
                            // Automatically submit the form to save the widget to custom_dashboard_widgets
                            // This ensures the widget is saved to database and will appear in Custom Widgets section on next page load
                            $('#wac-settings-form').submit();
                        });
                    }
                });
                
                // Hover effect for delete button
                $(document).on('mouseenter', '.wac-delete-custom-widget', function() {
                    $(this).css('opacity', '1');
                }).on('mouseleave', '.wac-delete-custom-widget', function() {
                    $(this).css('opacity', '0.7');
                });
                
                // Hover effect for move to custom button
                $(document).on('mouseenter', '.wac-move-to-custom-widget', function() {
                    $(this).css('opacity', '1');
                }).on('mouseleave', '.wac-move-to-custom-widget', function() {
                    $(this).css('opacity', '0.7');
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
        </div>
        <!-- End Dashboard Widgets Sub-tab -->

        <!-- Dashboard Toolbar Sub-tab -->
        <div id="dashboard-toolbar" class="wac-sub-tab-content <?php echo $active_sub_tab === 'dashboard-toolbar' ? 'active' : ''; ?>">
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
            
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e5ea">
                <label class="wac-checkbox-item">
                    <span>
                        <strong>Hide Admin Toolbar in wp-admin</strong>
                        <small style="display:block;color:#86868b;font-size:11px;margin-top:2px">Completely hide the admin toolbar in WordPress admin area (no space left)</small>
                    </span>
                    <input type="hidden" name="wac_settings[hide_admin_bar_in_admin]" value="0">
                    <input type="checkbox" name="wac_settings[hide_admin_bar_in_admin]" value="1" 
                           <?php checked( ! empty( $opt['hide_admin_bar_in_admin'] ) ); ?>>
                </label>
                
                <label class="wac-checkbox-item" style="margin-top:12px">
                    <span>
                        <strong>Hide Admin Toolbar on Frontend</strong>
                        <small style="display:block;color:#86868b;font-size:11px;margin-top:2px">Completely hide the admin toolbar on frontend for all users including admins (no space left, content starts from top)</small>
                    </span>
                    <input type="hidden" name="wac_settings[hide_admin_bar_frontend]" value="0">
                    <input type="checkbox" name="wac_settings[hide_admin_bar_frontend]" value="1" 
                           <?php checked( ! empty( $opt['hide_admin_bar_frontend'] ) ); ?>>
                </label>
            </div>
        </div>
        </div>
        <!-- End Dashboard Toolbar Sub-tab -->

        <!-- Dashboard General Sub-tab -->
        <div id="dashboard-general" class="wac-sub-tab-content <?php echo $active_sub_tab === 'dashboard-general' ? 'active' : ''; ?>">
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
        </div>
        <!-- End Dashboard General Sub-tab -->
        <?php
    }

    private function tab_productivity( $opt, $is_pro ) {
        // Sub-tabs for Productivity
        $sub_tabs = array(
            'productivity-command-palette' => 'Command Palette',
            'productivity-duplicate' => 'Duplicate Posts',
            'productivity-columns' => 'Admin Columns',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'productivity-command-palette';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'productivity-command-palette';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Productivity Command Palette Sub-tab -->
        <div id="productivity-command-palette" class="wac-sub-tab-content <?php echo $active_sub_tab === 'productivity-command-palette' ? 'active' : ''; ?>">
        <!-- Command Palette -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Command Palette</h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 16px;font-size:13px">
                Press <kbd style="background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px">⌘/Ctrl + Shift + P</kbd> anywhere in admin to quickly search posts, pages, users, and navigate to any setting.
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
                    <small>Display ⌘⇧P button in the top admin bar</small>
                    <?php if ( ! empty( $opt['hide_admin_bar_in_admin'] ) ) : ?>
                        <small style="display:block;color:#ff3b30;margin-top:4px">⚠️ Admin bar is hidden - this option is disabled</small>
                    <?php endif; ?>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[command_palette_show_admin_bar_icon]" value="1"
                           <?php checked( ! isset( $opt['command_palette_show_admin_bar_icon'] ) || $opt['command_palette_show_admin_bar_icon'] == '1' ); ?>
                           <?php disabled( ! empty( $opt['hide_admin_bar_in_admin'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <?php else : ?>
            <div class="wac-feature-preview">
                <img src="<?php echo WAC_PLUGIN_URL; ?>assets/img/preview-command.png" alt="" onerror="this.style.display='none'">
                <div class="wac-preview-placeholder" style="display:flex;flex-direction:column;align-items:center;gap:16px;padding:40px 20px;text-align:center">
                    <span class="dashicons dashicons-search" style="font-size:48px;color:#86868b"></span>
                    <p style="margin:0">Search anything with Cmd/Ctrl + Shift + P</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        </div>
        <!-- End Productivity Command Palette Sub-tab -->

        <!-- Productivity Duplicate Sub-tab -->
        <div id="productivity-duplicate" class="wac-sub-tab-content <?php echo $active_sub_tab === 'productivity-duplicate' ? 'active' : ''; ?>">
        <!-- Duplicate Posts -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Duplicate Posts</h2>
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
        </div>
        <!-- End Productivity Duplicate Sub-tab -->

        <!-- Productivity Columns Sub-tab -->
        <div id="productivity-columns" class="wac-sub-tab-content <?php echo $active_sub_tab === 'productivity-columns' ? 'active' : ''; ?>">
        <!-- Admin Columns -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Admin Columns</h2>
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
        </div>
        <!-- End Productivity Columns Sub-tab -->
        <?php
    }

    private function tab_menus( $opt, $is_pro ) {
        $roles = wac_get_roles();
        unset( $roles['administrator'] );
        $role_settings = isset( $opt['role_menu_settings'] ) ? $opt['role_menu_settings'] : array();
        $redirects = isset( $opt['login_redirect'] ) ? $opt['login_redirect'] : array();
        
        $menus = WAC_Role_Manager::get_default_menu_items();
        ?>
        
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
        // Sub-tabs for Appearance
        $sub_tabs = array(
            'appearance-theme' => 'Theme',
            'appearance-white-label' => 'White Label',
            'appearance-login' => 'Login',
            'appearance-css' => 'Custom CSS',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'appearance-theme';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'appearance-theme';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Appearance Theme Sub-tab -->
        <div id="appearance-theme" class="wac-sub-tab-content <?php echo $active_sub_tab === 'appearance-theme' ? 'active' : ''; ?>">
        <!-- Admin Theme -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Admin Theme</h2>
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
        </div>
        <!-- End Appearance Theme Sub-tab -->

        <!-- Appearance White Label Sub-tab -->
        <div id="appearance-white-label" class="wac-sub-tab-content <?php echo $active_sub_tab === 'appearance-white-label' ? 'active' : ''; ?>">
        <!-- White Label Admin -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>White Label Admin <span class="wac-pro-badge-small">PRO</span></h2>
                <?php if ( ! $is_pro ) : ?>
                    <a href="<?php echo esc_url( wac_fs()->get_upgrade_url() ); ?>" class="wac-unlock-btn">Unlock</a>
                <?php endif; ?>
            </div>
            <p style="color:#86868b;margin:-8px 0 20px;font-size:13px">
                Replace WordPress branding with your own logo, footer text, and styling. Enable White Label to unlock all options below.
            </p>
            <?php if ( $is_pro ) : ?>
            <div class="wac-row" style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #e5e5ea">
                <div>
                    <div class="wac-row-label" style="font-weight:600;margin-bottom:4px">Enable White Label</div>
                    <p style="margin:0;font-size:11px;color:#86868b">Activate white label features to customize admin branding</p>
                </div>
                <label class="wac-switch">
                    <input type="checkbox" name="wac_settings[white_label_enabled]" id="wac-white-label-enabled" value="1"
                           <?php checked( ! empty( $opt['white_label_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            <table class="form-table" id="wac-white-label-settings">
                <tr>
                    <th colspan="2" style="padding:16px 0 8px;font-size:13px;font-weight:600;color:#1d1d1f;border-bottom:1px solid #e5e5ea;margin-bottom:16px">
                        Admin Branding
                    </th>
                </tr>
                <tr>
                    <th style="padding-top:16px">Admin Logo</th>
                    <td style="padding-top:16px">
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[custom_admin_logo]" id="wac-custom-admin-logo" value="<?php echo esc_url( $opt['custom_admin_logo'] ?? '' ); ?>" class="regular-text" placeholder="https://" <?php echo empty( $opt['white_label_enabled'] ) ? 'disabled' : ''; ?>>
                            <button type="button" class="wac-media-btn" id="wac-admin-logo-btn" <?php echo empty( $opt['white_label_enabled'] ) ? 'disabled' : ''; ?>>Select</button>
                        </div>
                        <?php if ( ! empty( $opt['custom_admin_logo'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['custom_admin_logo'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                        <p class="description" style="margin-top:8px;font-size:11px;color:#86868b">Replace the WordPress logo in the admin menu and admin bar. Recommended: 200x50px transparent PNG</p>
                    </td>
                </tr>
                <tr>
                    <th>Hide WordPress Logo</th>
                    <td>
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="wac_settings[hide_wp_logo]" id="wac-hide-wp-logo" value="1" <?php checked( ! empty( $opt['hide_wp_logo'] ) ); ?> <?php echo empty( $opt['white_label_enabled'] ) ? 'disabled' : ''; ?>>
                            <span>Remove WordPress logo from the admin bar</span>
                        </label>
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Hides the WordPress logo icon from the top admin bar</p>
                    </td>
                </tr>
                <tr>
                    <th colspan="2" style="padding:24px 0 8px;font-size:13px;font-weight:600;color:#1d1d1f;border-bottom:1px solid #e5e5ea;margin-top:8px">
                        Footer Branding
                    </th>
                </tr>
                <tr>
                    <th style="padding-top:16px">Footer Text</th>
                    <td style="padding-top:16px">
                        <input type="text" name="wac_settings[custom_footer_text]" id="wac-custom-footer-text" value="<?php echo esc_attr( $opt['custom_footer_text'] ?? '' ); ?>" class="regular-text" placeholder="Powered by Your Company" <?php echo empty( $opt['white_label_enabled'] ) ? 'disabled' : ''; ?>>
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Replace the default "Thank you for creating with WordPress" footer text</p>
                    </td>
                </tr>
            </table>
            <script>
            jQuery(function($) {
                $('#wac-white-label-enabled').on('change', function() {
                    var isEnabled = $(this).is(':checked');
                    $('#wac-white-label-settings input, #wac-white-label-settings button').prop('disabled', !isEnabled);
                    if (!isEnabled) {
                        $('#wac-white-label-settings input[type="text"], #wac-white-label-settings input[type="url"]').val('');
                        $('#wac-white-label-settings input[type="checkbox"]').prop('checked', false);
                    }
                });
            });
            </script>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom admin logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom footer text</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Hide WordPress logo from admin bar</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Remove WordPress branding completely</div>
            </div>
            <?php endif; ?>
        </div>
        </div>
        <!-- End Appearance White Label Sub-tab -->

        <!-- Appearance Login Sub-tab -->
        <div id="appearance-login" class="wac-sub-tab-content <?php echo $active_sub_tab === 'appearance-login' ? 'active' : ''; ?>">
        <!-- Login Page Design -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Page Design</h2>
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
                        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e5e5ea">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#86868b;margin-bottom:4px">Position</label>
                                    <select name="wac_settings[login_bg_position]" style="width:100%;padding:6px 8px;border:1px solid #d1d1d6;border-radius:6px;font-size:12px">
                                        <option value="center center" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'center center' ); ?>>Center</option>
                                        <option value="center top" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'center top' ); ?>>Center Top</option>
                                        <option value="center bottom" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'center bottom' ); ?>>Center Bottom</option>
                                        <option value="left center" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'left center' ); ?>>Left Center</option>
                                        <option value="right center" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'right center' ); ?>>Right Center</option>
                                        <option value="left top" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'left top' ); ?>>Left Top</option>
                                        <option value="right top" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'right top' ); ?>>Right Top</option>
                                        <option value="left bottom" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'left bottom' ); ?>>Left Bottom</option>
                                        <option value="right bottom" <?php selected( ( $opt['login_bg_position'] ?? 'center center' ), 'right bottom' ); ?>>Right Bottom</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#86868b;margin-bottom:4px">Size</label>
                                    <select name="wac_settings[login_bg_size]" style="width:100%;padding:6px 8px;border:1px solid #d1d1d6;border-radius:6px;font-size:12px">
                                        <option value="cover" <?php selected( ( $opt['login_bg_size'] ?? 'cover' ), 'cover' ); ?>>Cover</option>
                                        <option value="contain" <?php selected( ( $opt['login_bg_size'] ?? 'cover' ), 'contain' ); ?>>Contain</option>
                                        <option value="auto" <?php selected( ( $opt['login_bg_size'] ?? 'cover' ), 'auto' ); ?>>Auto</option>
                                    </select>
                                </div>
                            </div>
                            <div style="margin-bottom:12px">
                                <label style="display:block;font-size:11px;font-weight:600;color:#86868b;margin-bottom:4px">Repeat</label>
                                <select name="wac_settings[login_bg_repeat]" style="width:100%;padding:6px 8px;border:1px solid #d1d1d6;border-radius:6px;font-size:12px">
                                    <option value="no-repeat" <?php selected( ( $opt['login_bg_repeat'] ?? 'no-repeat' ), 'no-repeat' ); ?>>No Repeat</option>
                                    <option value="repeat" <?php selected( ( $opt['login_bg_repeat'] ?? 'no-repeat' ), 'repeat' ); ?>>Repeat</option>
                                    <option value="repeat-x" <?php selected( ( $opt['login_bg_repeat'] ?? 'no-repeat' ), 'repeat-x' ); ?>>Repeat X</option>
                                    <option value="repeat-y" <?php selected( ( $opt['login_bg_repeat'] ?? 'no-repeat' ), 'repeat-y' ); ?>>Repeat Y</option>
                                </select>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#86868b;margin-bottom:4px">Overlay Color</label>
                                    <input type="color" name="wac_settings[login_bg_overlay]" value="<?php echo esc_attr( $opt['login_bg_overlay'] ?? '#000000' ); ?>" style="width:100%;height:36px;border:1px solid #d1d1d6;border-radius:6px;cursor:pointer">
                                </div>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#86868b;margin-bottom:4px">Overlay Opacity</label>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <input type="range" name="wac_settings[login_bg_overlay_opacity]" min="0" max="100" value="<?php echo esc_attr( $opt['login_bg_overlay_opacity'] ?? 0 ); ?>" style="flex:1" oninput="this.nextElementSibling.value=this.value">
                                        <input type="number" min="0" max="100" value="<?php echo esc_attr( $opt['login_bg_overlay_opacity'] ?? 0 ); ?>" style="width:60px;padding:6px 8px;border:1px solid #d1d1d6;border-radius:6px;font-size:12px" oninput="this.previousElementSibling.value=this.value">
                                        <span style="font-size:11px;color:#86868b">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                <tr>
                    <th>Login Button Text</th>
                    <td>
                        <input type="text" name="wac_settings[login_btn_text]" value="<?php echo esc_attr( $opt['login_btn_text'] ?? 'Log In' ); ?>" class="regular-text" placeholder="Log In">
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Custom text for the login button</p>
                    </td>
                </tr>
                <tr>
                    <th>Form Settings</th>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:12px">
                            <label style="display:flex;align-items:center;gap:8px">
                                <input type="checkbox" name="wac_settings[login_hide_remember]" value="1" <?php checked( ! empty( $opt['login_hide_remember'] ) ); ?>>
                                <span>Hide "Remember Me" checkbox</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px">
                                <input type="checkbox" name="wac_settings[login_hide_lost_password]" value="1" <?php checked( ! empty( $opt['login_hide_lost_password'] ) ); ?>>
                                <span>Hide "Lost your password?" link</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px">
                                <input type="checkbox" name="wac_settings[login_hide_back_to_site]" value="1" <?php checked( ! empty( $opt['login_hide_back_to_site'] ) ); ?>>
                                <span>Hide "Back to [Site]" link</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px">
                                <input type="checkbox" name="wac_settings[login_hide_register]" value="1" <?php checked( ! empty( $opt['login_hide_register'] ) ); ?>>
                                <span>Hide "Register" link</span>
                            </label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Form Width</th>
                    <td>
                        <input type="number" name="wac_settings[login_form_width]" value="<?php echo esc_attr( $opt['login_form_width'] ?? '320' ); ?>" min="280" max="600" step="10" style="width:80px"> px
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Login form width (default: 320px)</p>
                    </td>
                </tr>
                <tr>
                    <th>Form Border Radius</th>
                    <td>
                        <input type="number" name="wac_settings[login_form_radius]" value="<?php echo esc_attr( $opt['login_form_radius'] ?? '0' ); ?>" min="0" max="20" step="1" style="width:80px"> px
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Border radius for login form (0-20px)</p>
                    </td>
                </tr>
                <tr>
                    <th>Form Shadow</th>
                    <td>
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="wac_settings[login_form_shadow]" value="1" <?php checked( ! empty( $opt['login_form_shadow'] ) ); ?>>
                            <span>Enable box shadow on login form</span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Custom Login Message</th>
                    <td>
                        <textarea name="wac_settings[login_custom_message]" rows="3" class="large-text" placeholder="Enter a custom message to display above the login form"><?php echo esc_textarea( $opt['login_custom_message'] ?? '' ); ?></textarea>
                        <p class="description" style="margin-top:4px;font-size:11px;color:#86868b">Optional message displayed above the login form</p>
                    </td>
                </tr>
            </table>
            <div style="margin-top:20px;padding:16px;background:#f5f5f7;border-radius:8px;border-left:3px solid #007aff">
                <p style="margin:0;font-size:13px;color:#1d1d1f;font-weight:500;margin-bottom:4px">💡 Login Redirect</p>
                <p style="margin:0;font-size:12px;color:#86868b;line-height:1.5">
                    Want to redirect users after login? Use <strong>Menus & Roles → Login Redirect</strong> for role-based redirects with more control.
                </p>
            </div>
            <?php else : ?>
            <div class="wac-feature-list">
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom login logo</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Background image or color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Custom button color</div>
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Form background styling</div>
            </div>
            <?php endif; ?>
        </div>
        </div>
        <!-- End Appearance Login Sub-tab -->

        <!-- Appearance CSS Sub-tab -->
        <div id="appearance-css" class="wac-sub-tab-content <?php echo $active_sub_tab === 'appearance-css' ? 'active' : ''; ?>">
        <!-- Custom CSS -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Custom CSS</h2>
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
        </div>
        <!-- End Appearance CSS Sub-tab -->
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
            
            <table class="form-table" id="wac-maintenance-fields">
                <tr>
                    <th>Title</th>
                    <td><input type="text" name="wac_settings[maintenance_title]" value="<?php echo esc_attr( $opt['maintenance_title'] ?? '' ); ?>" class="regular-text" placeholder="We'll be right back" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>></td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td><textarea name="wac_settings[maintenance_message]" rows="2" class="large-text" placeholder="Our site is currently undergoing scheduled maintenance." <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>><?php echo esc_textarea( $opt['maintenance_message'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                    <th>Background</th>
                    <td>
                        <div class="wac-color-row">
                            <div class="wac-color-item">
                                <label>Color</label>
                                <input type="color" name="wac_settings[maintenance_bg_color]" value="<?php echo esc_attr( $opt['maintenance_bg_color'] ?? '#ffffff' ); ?>" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="wac-color-item">
                                <label>Text</label>
                                <input type="color" name="wac_settings[maintenance_text_color]" value="<?php echo esc_attr( $opt['maintenance_text_color'] ?? '#1d1d1f' ); ?>" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[maintenance_bg_image]" value="<?php echo esc_url( $opt['maintenance_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="Optional" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>
                            <button type="button" class="wac-media-btn" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>Select</button>
                        </div>
                        <?php if ( ! empty( $opt['maintenance_bg_image'] ) ) : ?>
                            <div class="wac-media-preview"><img src="<?php echo esc_url( $opt['maintenance_bg_image'] ); ?>"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <script>
            jQuery(function($) {
                var $toggle = $('input[name="wac_settings[maintenance_enabled]"]');
                var $fields = $('#wac-maintenance-fields');
                
                function toggleFields() {
                    var isEnabled = $toggle.is(':checked');
                    $fields.find('input, textarea, button').prop('disabled', !isEnabled);
                    $fields.css('opacity', isEnabled ? '1' : '0.6');
                }
                
                // Initial state
                toggleFields();
                
                // On toggle change
                $toggle.on('change', toggleFields);
            });
            </script>
        </div>
        <?php
    }

    private function tab_tools( $opt, $is_pro ) {
        // Sub-tabs for Tools
        $sub_tabs = array(
            'tools-activity-log' => 'Activity Log',
            'tools-export-import' => 'Export/Import',
            'tools-reset' => 'Reset',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'tools-activity-log';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'tools-activity-log';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Tools Activity Log Sub-tab -->
        <div id="tools-activity-log" class="wac-sub-tab-content <?php echo $active_sub_tab === 'tools-activity-log' ? 'active' : ''; ?>">
        <!-- Activity Log -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Activity Log</h2>
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
        </div>
        <!-- End Tools Activity Log Sub-tab -->

        <!-- Tools Export/Import Sub-tab -->
        <div id="tools-export-import" class="wac-sub-tab-content <?php echo $active_sub_tab === 'tools-export-import' ? 'active' : ''; ?>">
        <!-- Export/Import -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Export / Import</h2>
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
        </div>
        <!-- End Tools Export/Import Sub-tab -->

        <!-- Tools Reset Sub-tab -->
        <div id="tools-reset" class="wac-sub-tab-content <?php echo $active_sub_tab === 'tools-reset' ? 'active' : ''; ?>">
        <div class="wac-settings-section">
            <h2>Reset</h2>
            <p style="margin:0 0 12px;color:#86868b;">Reset all settings to defaults. This includes Menu Editor customizations.</p>
            <?php if ( isset( $_GET['reset'] ) && $_GET['reset'] == '1' ) : ?>
                <div class="notice notice-success is-dismissible" style="margin:12px 0;">
                    <p>All settings have been reset to defaults.</p>
                </div>
            <?php endif; ?>
            <form method="post" style="display:inline">
                <?php wp_nonce_field( 'wac_reset', '_wac_reset' ); ?>
                <button type="submit" name="wac_reset" class="button button-primary" onclick="return confirm('Are you sure? This will reset ALL settings including Menu Editor customizations. This cannot be undone.');">Reset All Settings</button>
            </form>
        </div>
        </div>
        <!-- End Tools Reset Sub-tab -->
        <?php
    }

    private function tab_security( $opt, $is_pro ) {
        $security = class_exists( 'WAC_Security_Tweaks' ) ? WAC_Security_Tweaks::get_options() : array();
        
        // Sub-tabs for Security
        $sub_tabs = array(
            'security-tweaks' => 'Security Tweaks',
            'security-login-protection' => 'Login Protection',
            'security-login-history' => 'Login History',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'security-tweaks';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'security-tweaks';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Security Tweaks Sub-tab -->
        <div id="security-tweaks" class="wac-sub-tab-content <?php echo $active_sub_tab === 'security-tweaks' ? 'active' : ''; ?>">
        <!-- Security Tweaks -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Security Tweaks</h2>
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
        </div>
        <!-- End Security Tweaks Sub-tab -->

        <!-- Security Login Protection Sub-tab -->
        <div id="security-login-protection" class="wac-sub-tab-content <?php echo $active_sub_tab === 'security-login-protection' ? 'active' : ''; ?>">
        <!-- Login Protection -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login Protection</h2>
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
        </div>
        <!-- End Security Login Protection Sub-tab -->

        <!-- Security Login History Sub-tab -->
        <div id="security-login-history" class="wac-sub-tab-content <?php echo $active_sub_tab === 'security-login-history' ? 'active' : ''; ?>">
        <!-- Login History -->
        <div class="wac-settings-section <?php echo ! $is_pro ? 'wac-locked' : ''; ?>">
            <div class="wac-section-header">
                <h2>Login History</h2>
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
        </div>
        <!-- End Security Login History Sub-tab -->
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
                <h2>Login Page Design</h2>
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
                <h2>Custom CSS</h2>
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
        // Sub-tabs for Cleanup
        $sub_tabs = array(
            'cleanup-database' => 'Database',
            'cleanup-media' => 'Media',
            'cleanup-maintenance' => 'Maintenance',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'cleanup-database';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'cleanup-database';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Cleanup Database Sub-tab -->
        <div id="cleanup-database" class="wac-sub-tab-content <?php echo $active_sub_tab === 'cleanup-database' ? 'active' : ''; ?>">
        <?php
        // Database Cleanup
        $this->tab_database_cleanup( $opt, $is_pro );
        ?>
        </div>
        <!-- End Cleanup Database Sub-tab -->

        <!-- Cleanup Media Sub-tab -->
        <div id="cleanup-media" class="wac-sub-tab-content <?php echo $active_sub_tab === 'cleanup-media' ? 'active' : ''; ?>">
        <?php
        // Media Cleanup
        $this->tab_media_cleanup( $opt, $is_pro );
        ?>
        </div>
        <!-- End Cleanup Media Sub-tab -->

        <!-- Cleanup Maintenance Sub-tab -->
        <div id="cleanup-maintenance" class="wac-sub-tab-content <?php echo $active_sub_tab === 'cleanup-maintenance' ? 'active' : ''; ?>">
        <?php
        // Maintenance Mode
        $this->tab_maintenance( $opt, $is_pro );
        ?>
        </div>
        <!-- End Cleanup Maintenance Sub-tab -->
        <?php
    }

    /**
     * Tab: Menus & Roles - Menu Editor, Role Editor, Role-based menus
     */
    private function tab_menus_roles( $opt, $is_pro ) {
        // Sub-tabs for Menus & Roles
        $sub_tabs = array(
            'menus-roles-menu-editor' => 'Menu Editor',
            'menus-roles-role-editor' => 'Role Editor',
            'menus-roles-login-redirect' => 'Login Redirect',
        );
        
        // Get active sub-tab from hash or default
        $active_sub_tab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'menus-roles-menu-editor';
        if ( ! isset( $sub_tabs[ $active_sub_tab ] ) ) {
            $active_sub_tab = 'menus-roles-menu-editor';
        }
        
        $this->render_sub_tabs( $sub_tabs, $active_sub_tab );
        ?>
        
        <!-- Menus & Roles Menu Editor Sub-tab -->
        <div id="menus-roles-menu-editor" class="wac-sub-tab-content <?php echo $active_sub_tab === 'menus-roles-menu-editor' ? 'active' : ''; ?>">
        <?php
        // Menu Editor
        if ( $is_pro && class_exists( 'WAC_Menu_Editor' ) ) {
            echo '<div class="wac-settings-section">';
            WAC_Menu_Editor::render_ui();
            echo '</div>';
        } else {
            echo '<div class="wac-settings-section ' . ( ! $is_pro ? 'wac-locked' : '' ) . '">';
            echo '<div class="wac-section-header"><h2>Admin Menu Editor</h2>';
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
        ?>
        </div>
        <!-- End Menus & Roles Menu Editor Sub-tab -->

        <!-- Menus & Roles Role Editor Sub-tab -->
        <div id="menus-roles-role-editor" class="wac-sub-tab-content <?php echo $active_sub_tab === 'menus-roles-role-editor' ? 'active' : ''; ?>">
        <?php
        // Role Editor
        if ( $is_pro && class_exists( 'WAC_Role_Editor' ) ) {
            echo '<div class="wac-settings-section">';
            WAC_Role_Editor::render_ui();
            echo '</div>';
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
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Delete unused roles</div>';
            echo '<div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Visual capability editor</div>';
            echo '</div></div>';
        }
        ?>
        </div>
        <!-- End Menus & Roles Role Editor Sub-tab -->

        <!-- Menus & Roles Login Redirect Sub-tab -->
        <div id="menus-roles-login-redirect" class="wac-sub-tab-content <?php echo $active_sub_tab === 'menus-roles-login-redirect' ? 'active' : ''; ?>">
        <?php
        // Login Redirect is already in tab_menus, but we'll extract it
        $redirects = isset( $opt['login_redirect'] ) ? $opt['login_redirect'] : array();
        ?>
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
        </div>
        <!-- End Menus & Roles Login Redirect Sub-tab -->
        <?php
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
                <h2>Login Page Design</h2>
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
                Press <kbd style="background:#e5e5ea;padding:2px 6px;border-radius:4px;font-size:11px">⌘/Ctrl + Shift + P</kbd> anywhere in admin to quickly search posts, pages, users, and navigate to any setting.
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
                <div class="wac-feature-item"><span class="dashicons dashicons-yes-alt"></span> Quick search with Cmd/Ctrl + Shift + P</div>
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
                        <input type="hidden" name="wac_settings[<?php echo esc_attr( $key ); ?>]" value="0">
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
        // In Free version, Pro features are visible but disabled
        $pro_disabled = ! $is_pro;
        ?>
        <div class="wac-settings-section">
            <h2>Maintenance Mode</h2>
            <div class="wac-row" style="margin-bottom:16px">
                <div class="wac-row-label">
                    Enable Maintenance Mode
                    <small>Display a maintenance page to visitors while logged-in users can access the site</small>
                </div>
                <label class="wac-switch">
                    <input type="hidden" name="wac_settings[maintenance_enabled]" value="0">
                    <input type="checkbox" name="wac_settings[maintenance_enabled]" value="1"
                           <?php checked( ! empty( $opt['maintenance_enabled'] ) ); ?>>
                    <span class="wac-switch-slider"></span>
                </label>
            </div>
            
            <table class="form-table" id="wac-maintenance-fields">
                <tr>
                    <th>Logo<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[maintenance_logo]" value="<?php echo esc_url( $opt['maintenance_logo'] ?? '' ); ?>" class="regular-text" placeholder="Optional logo image URL" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <button type="button" class="wac-media-btn" data-target="maintenance_logo" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>Select</button>
                        </div>
                        <?php if ( ! empty( $opt['maintenance_logo'] ) ) : ?>
                            <div class="wac-media-preview" style="margin-top:8px"><img src="<?php echo esc_url( $opt['maintenance_logo'] ); ?>" style="max-width:200px;height:auto;border-radius:4px"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Title</th>
                    <td><input type="text" name="wac_settings[maintenance_title]" value="<?php echo esc_attr( $opt['maintenance_title'] ?? '' ); ?>" class="regular-text" placeholder="We'll be right back" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>></td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td>
                        <?php if ( $is_pro ) : ?>
                            <?php
                            $content = isset( $opt['maintenance_message'] ) ? wp_kses_post( $opt['maintenance_message'] ) : '';
                            $editor_id = 'maintenance_message';
                            $settings = array(
                                'textarea_name' => 'wac_settings[maintenance_message]',
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist',
                                    'toolbar2' => '',
                                ),
                                'quicktags' => false,
                            );
                            if ( empty( $opt['maintenance_enabled'] ) ) {
                                $settings['editor_class'] = 'disabled';
                            }
                            wp_editor( $content, $editor_id, $settings );
                            ?>
                        <?php else : ?>
                            <textarea name="wac_settings[maintenance_message]" rows="4" class="large-text" placeholder="Our site is currently undergoing scheduled maintenance." <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>><?php echo esc_textarea( $opt['maintenance_message'] ?? '' ); ?></textarea>
                            <p style="margin-top:4px;font-size:11px;color:#86868b">💡 <strong>Pro feature:</strong> Rich text editor with formatting options available in Pro version</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Countdown Timer<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <input type="checkbox" name="wac_settings[maintenance_countdown_enabled]" value="1" <?php checked( ! empty( $opt['maintenance_countdown_enabled'] ) ); ?> <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <span>Enable countdown timer</span>
                        </label>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Date</label>
                                <input type="date" name="wac_settings[maintenance_countdown_date]" value="<?php echo esc_attr( $opt['maintenance_countdown_date'] ?? '' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Time</label>
                                <input type="time" name="wac_settings[maintenance_countdown_time]" value="<?php echo esc_attr( $opt['maintenance_countdown_time'] ?? '' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Countdown Label</label>
                                <input type="text" name="wac_settings[maintenance_countdown_label]" value="<?php echo esc_attr( $opt['maintenance_countdown_label'] ?? "We'll be back in" ); ?>" class="regular-text" placeholder="We'll be back in" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Days Label</label>
                                <input type="text" name="wac_settings[maintenance_countdown_days_label]" value="<?php echo esc_attr( $opt['maintenance_countdown_days_label'] ?? 'Days' ); ?>" class="regular-text" placeholder="Days" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Hours Label</label>
                                <input type="text" name="wac_settings[maintenance_countdown_hours_label]" value="<?php echo esc_attr( $opt['maintenance_countdown_hours_label'] ?? 'Hours' ); ?>" class="regular-text" placeholder="Hours" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Minutes Label</label>
                                <input type="text" name="wac_settings[maintenance_countdown_minutes_label]" value="<?php echo esc_attr( $opt['maintenance_countdown_minutes_label'] ?? 'Minutes' ); ?>" class="regular-text" placeholder="Minutes" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Seconds Label</label>
                                <input type="text" name="wac_settings[maintenance_countdown_seconds_label]" value="<?php echo esc_attr( $opt['maintenance_countdown_seconds_label'] ?? 'Seconds' ); ?>" class="regular-text" placeholder="Seconds" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Background Color</th>
                    <td>
                        <input type="color" name="wac_settings[maintenance_bg_color]" value="<?php echo esc_attr( $opt['maintenance_bg_color'] ?? '#ffffff' ); ?>" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <th>Background Image</th>
                    <td>
                        <div class="wac-media-field">
                            <input type="url" name="wac_settings[maintenance_bg_image]" value="<?php echo esc_url( $opt['maintenance_bg_image'] ?? '' ); ?>" class="regular-text" placeholder="Optional" <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>
                            <button type="button" class="wac-media-btn" <?php echo $is_pro ? 'data-target="maintenance_bg_image"' : ''; ?> <?php echo empty( $opt['maintenance_enabled'] ) ? 'disabled' : ''; ?>>Select</button>
                        </div>
                        <?php if ( ! empty( $opt['maintenance_bg_image'] ) ) : ?>
                            <div class="wac-media-preview" style="margin-top:8px"><img src="<?php echo esc_url( $opt['maintenance_bg_image'] ); ?>" style="max-width:300px;height:auto;border-radius:4px"></div>
                        <?php else : ?>
                            <div class="wac-media-preview"></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Background Position<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <select name="wac_settings[maintenance_bg_position]" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <option value="center center" <?php selected( $opt['maintenance_bg_position'] ?? 'center center', 'center center' ); ?>>Center</option>
                            <option value="top left" <?php selected( $opt['maintenance_bg_position'] ?? '', 'top left' ); ?>>Top Left</option>
                            <option value="top center" <?php selected( $opt['maintenance_bg_position'] ?? '', 'top center' ); ?>>Top Center</option>
                            <option value="top right" <?php selected( $opt['maintenance_bg_position'] ?? '', 'top right' ); ?>>Top Right</option>
                            <option value="center left" <?php selected( $opt['maintenance_bg_position'] ?? '', 'center left' ); ?>>Center Left</option>
                            <option value="center right" <?php selected( $opt['maintenance_bg_position'] ?? '', 'center right' ); ?>>Center Right</option>
                            <option value="bottom left" <?php selected( $opt['maintenance_bg_position'] ?? '', 'bottom left' ); ?>>Bottom Left</option>
                            <option value="bottom center" <?php selected( $opt['maintenance_bg_position'] ?? '', 'bottom center' ); ?>>Bottom Center</option>
                            <option value="bottom right" <?php selected( $opt['maintenance_bg_position'] ?? '', 'bottom right' ); ?>>Bottom Right</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Background Size<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <select name="wac_settings[maintenance_bg_size]" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <option value="cover" <?php selected( $opt['maintenance_bg_size'] ?? 'cover', 'cover' ); ?>>Cover</option>
                            <option value="contain" <?php selected( $opt['maintenance_bg_size'] ?? '', 'contain' ); ?>>Contain</option>
                            <option value="auto" <?php selected( $opt['maintenance_bg_size'] ?? '', 'auto' ); ?>>Auto</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Background Repeat<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <select name="wac_settings[maintenance_bg_repeat]" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <option value="no-repeat" <?php selected( $opt['maintenance_bg_repeat'] ?? 'no-repeat', 'no-repeat' ); ?>>No Repeat</option>
                            <option value="repeat" <?php selected( $opt['maintenance_bg_repeat'] ?? '', 'repeat' ); ?>>Repeat</option>
                            <option value="repeat-x" <?php selected( $opt['maintenance_bg_repeat'] ?? '', 'repeat-x' ); ?>>Repeat X</option>
                            <option value="repeat-y" <?php selected( $opt['maintenance_bg_repeat'] ?? '', 'repeat-y' ); ?>>Repeat Y</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Background Attachment<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <select name="wac_settings[maintenance_bg_attachment]" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            <option value="scroll" <?php selected( $opt['maintenance_bg_attachment'] ?? 'scroll', 'scroll' ); ?>>Scroll</option>
                            <option value="fixed" <?php selected( $opt['maintenance_bg_attachment'] ?? '', 'fixed' ); ?>>Fixed</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Overlay<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Color</label>
                                <input type="color" name="wac_settings[maintenance_overlay_color]" value="<?php echo esc_attr( $opt['maintenance_overlay_color'] ?? '#000000' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Opacity (0-100)</label>
                                <input type="number" name="wac_settings[maintenance_overlay_opacity]" value="<?php echo esc_attr( $opt['maintenance_overlay_opacity'] ?? '0' ); ?>" min="0" max="100" style="width:100px" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Colors<?php if ( $pro_disabled ) : ?> <span class="wac-pro-badge-small">PRO</span><?php endif; ?></th>
                    <td>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Title Color</label>
                                <input type="color" name="wac_settings[maintenance_title_color]" value="<?php echo esc_attr( $opt['maintenance_title_color'] ?? '#1d1d1f' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Text Color</label>
                                <input type="color" name="wac_settings[maintenance_text_color]" value="<?php echo esc_attr( $opt['maintenance_text_color'] ?? '#86868b' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:4px;font-size:12px;color:#86868b">Countdown Color</label>
                                <input type="color" name="wac_settings[maintenance_countdown_color]" value="<?php echo esc_attr( $opt['maintenance_countdown_color'] ?? '#007aff' ); ?>" <?php echo ( empty( $opt['maintenance_enabled'] ) || $pro_disabled ) ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <style>
            #wac-maintenance-fields select:disabled {
                background-image: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
            }
            #wac-maintenance-fields select:disabled::-ms-expand {
                display: none !important;
            }
            </style>
            
            <script>
            jQuery(function($) {
                var $toggle = $('input[name="wac_settings[maintenance_enabled]"]');
                var $fields = $('#wac-maintenance-fields');
                var isPro = <?php echo $is_pro ? 'true' : 'false'; ?>;
                var proDisabled = <?php echo $pro_disabled ? 'true' : 'false'; ?>;
                
                function toggleFields() {
                    var isEnabled = $toggle.is(':checked');
                    // Disable all fields if maintenance is disabled
                    $fields.find('input, textarea, button, select').each(function() {
                        var $el = $(this);
                        // If it's a Pro feature and we're in Free version, always keep it disabled
                        var $row = $el.closest('tr');
                        var isProFeature = $row.find('th').text().indexOf('PRO') !== -1 || $el.data('target') === 'maintenance_logo';
                        if (proDisabled && isProFeature) {
                            $el.prop('disabled', true);
                        } else {
                            $el.prop('disabled', !isEnabled);
                        }
                    });
                    if (typeof tinymce !== 'undefined' && tinymce.get('maintenance_message')) {
                        tinymce.get('maintenance_message').setMode(isEnabled && isPro ? 'design' : 'readonly');
                    }
                    // Apply opacity: disabled fields or Pro features in Free version
                    $fields.find('tr').each(function() {
                        var $row = $(this);
                        var isProFeature = $row.find('th').text().indexOf('PRO') !== -1;
                        var $inputs = $row.find('input, textarea, button, select');
                        var allDisabled = $inputs.length > 0 && $inputs.filter(':not(:disabled)').length === 0;
                        if (allDisabled || (proDisabled && isProFeature)) {
                            $row.css('opacity', '0.6');
                        } else {
                            $row.css('opacity', isEnabled ? '1' : '0.6');
                        }
                    });
                }
                
                // Initial state
                toggleFields();
                
                // On toggle change
                $toggle.on('change', toggleFields);
                
                // Media uploader
                <?php if ( $is_pro ) : ?>
                $('.wac-media-btn[data-target]').on('click', function(e) {
                    e.preventDefault();
                    if ($(this).prop('disabled')) return;
                    var target = $(this).data('target');
                    var inputField = $(this).siblings('input[type="url"]');
                    var preview = $(this).closest('td').find('.wac-media-preview');
                    
                    var frame = wp.media({
                        title: 'Select Image',
                        button: { text: 'Use this image' },
                        multiple: false
                    });
                    
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        inputField.val(attachment.url);
                        if (preview.length) {
                            preview.html('<img src="' + attachment.url + '" style="max-width:300px;height:auto;border-radius:4px">');
                        }
                        frame.close();
                        if (typeof wacShowNotification === 'function') {
                            wacShowNotification('Image selected', 'success');
                        }
                    });
                    
                    frame.open();
                });
                <?php else : ?>
                // Free version: Maintenance Mode media uploader (only for background image, logo is disabled)
                // Note: General media uploader handler above will handle buttons without data-target
                // This handler only prevents logo button from working
                $('.wac-media-btn[data-target="maintenance_logo"]').on('click', function(e) {
                    e.preventDefault();
                    // Logo button is disabled in Free version, do nothing
                    return false;
                });
                <?php endif; ?>
            });
            </script>
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
