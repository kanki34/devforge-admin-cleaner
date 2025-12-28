<?php
/**
 * Admin Theme & Color Schemes
 * Dark mode, custom colors, and admin styling
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Admin_Theme {

    private static $instance = null;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load options fresh each time
        $this->refresh_options();
        
        // Add CSS early in admin_head for maximum compatibility - NO capability check!
        // Use priority 1 to inject BEFORE WordPress default styles
        add_action( 'admin_head', array( $this, 'inject_theme_css' ), 1 );
        // Also inject in wp_head for admin bar styling
        add_action( 'wp_head', array( $this, 'inject_theme_css' ), 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_theme_styles' ), 999 );
        add_action( 'admin_bar_menu', array( $this, 'add_theme_toggle' ), 100 );
        add_action( 'wp_ajax_wac_save_theme', array( $this, 'ajax_save_theme' ) );
        add_action( 'wp_ajax_wac_toggle_dark_mode', array( $this, 'ajax_toggle_dark_mode' ) );
    }
    
    /**
     * Refresh options from database
     */
    private function refresh_options() {
        $this->options = get_option( 'wac_admin_theme', array() );
    }
    
    /**
     * Inject theme CSS early in admin_head
     */
    public function inject_theme_css() {
        // Refresh options to get latest settings
        $this->refresh_options();
        
        $scheme = $this->get_current_scheme();
        $custom = $this->options;
        
        // Check for user-specific dark mode preference
        $user_dark = get_user_meta( get_current_user_id(), 'wac_dark_mode', true );
        if ( $user_dark === 'on' ) {
            $scheme = 'dark';
        } elseif ( $user_dark === 'off' ) {
            $scheme = 'default';
        }

        $css = $this->generate_css( $scheme, $custom );
        
        if ( ! empty( $css ) ) {
            // Output CSS with maximum specificity
            echo '<style id="wac-admin-theme" type="text/css">' . "\n";
            echo '/* Webtapot Admin Theme - ' . esc_html( $scheme ) . ' */' . "\n";
            // Direct output - CSS already has !important flags
            echo $css . "\n";
            echo '</style>';
        }
    }

    /**
     * Enqueue theme styles (fallback)
     */
    public function enqueue_theme_styles() {
        // CSS is already injected in admin_head via inject_theme_css()
        // This method is kept for backward compatibility
    }

    /**
     * Get current color scheme
     */
    private function get_current_scheme() {
        return $this->options['scheme'] ?? 'default';
    }

    /**
     * Generate CSS based on scheme and custom colors
     */
    private function generate_css( $scheme, $custom ) {
        $schemes = $this->get_schemes();
        $colors = isset( $schemes[ $scheme ] ) ? $schemes[ $scheme ] : array();
        
        // Get scheme colors first (these are the base colors for each scheme)
        $scheme_primary = $colors['primary'] ?? null;
        $scheme_secondary = $colors['secondary'] ?? null;
        $scheme_accent = $colors['accent'] ?? '#007aff';
        
        // Apply custom colors on top (custom always overrides scheme, but only if not empty)
        // Empty string means "use scheme default"
        $primary = ( ! empty( $custom['primary'] ) && trim( $custom['primary'] ) !== '' ) ? $custom['primary'] : $scheme_primary;
        $secondary = ( ! empty( $custom['secondary'] ) && trim( $custom['secondary'] ) !== '' ) ? $custom['secondary'] : $scheme_secondary;
        $accent = ( ! empty( $custom['accent'] ) && trim( $custom['accent'] ) !== '' ) ? $custom['accent'] : $scheme_accent;

        $css = '';
        
        // If default scheme but custom accent color is set, still apply accent color
        if ( $scheme === 'default' && ! empty( $custom['accent'] ) ) {
            $accent = $custom['accent'];
            $css .= "
            /* Custom Accent Color */
            #adminmenu li.current a.menu-top, 
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu { 
                background: {$accent} !important; 
                color: #fff !important;
            }
            .button-primary, .wp-core-ui .button-primary { 
                background: {$accent} !important; 
                border-color: {$accent} !important; 
            }
            .button-primary:hover, .wp-core-ui .button-primary:hover { 
                background: " . $this->darken_color( $accent, 10 ) . " !important; 
                border-color: " . $this->darken_color( $accent, 10 ) . " !important; 
            }
            a { color: {$accent} !important; }
            a:hover { color: " . $this->darken_color( $accent, 10 ) . " !important; }
            input[type=checkbox]:checked { accent-color: {$accent} !important; }
            input[type=radio]:checked { accent-color: {$accent} !important; }
            ";
            return $css;
        }
        
        // If default scheme and no custom accent, return empty
        if ( $scheme === 'default' && empty( $custom['accent'] ) ) {
            return '';
        }
        
        // Dark mode specific styles
        if ( $scheme === 'dark' ) {
            $accent_dark = $accent; // Use custom accent if set
            $css = "
            /* Dark Mode */
            body.wp-admin, html.wp-toolbar { background: #1a1a1a !important; }
            #wpcontent, #wpbody-content { background: #1a1a1a !important; }
            #adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap { background: #0d0d0d !important; }
            #adminmenu a { color: #a0a0a0 !important; }
            #adminmenu .wp-submenu a { color: #b0b0b0 !important; }
            #adminmenu li.menu-top:hover, #adminmenu li.opensub > a.menu-top, #adminmenu li > a.menu-top:focus { background: #252525 !important; }
            #adminmenu .wp-has-current-submenu .wp-submenu, #adminmenu .wp-has-current-submenu.opensub .wp-submenu { background: #1a1a1a !important; }
            #adminmenu .wp-submenu a:focus, #adminmenu .wp-submenu a:hover { color: #fff !important; }
            #adminmenu li.current a.menu-top, #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu { background: {$accent_dark} !important; color: #fff !important; }
            #wpadminbar { background: #0d0d0d !important; }
            #wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label { color: #a0a0a0 !important; }
            #wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item, #wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus { background: #252525 !important; color: #fff !important; }
            
            .wrap h1, .wrap h2 { color: #fff !important; }
            .postbox { background: #252525 !important; border-color: #333 !important; }
            .postbox .hndle, .postbox .handlediv { color: #e0e0e0 !important; }
            .postbox .inside { color: #c0c0c0 !important; }
            
            .widefat, .widefat td, .widefat th { background: #252525 !important; border-color: #333 !important; color: #c0c0c0 !important; }
            .widefat thead th, .widefat tfoot th { background: #1a1a1a !important; color: #e0e0e0 !important; }
            .striped > tbody > :nth-child(odd), ul.striped > :nth-child(odd) { background: #2a2a2a !important; }
            
            input[type=text], input[type=password], input[type=email], input[type=number], input[type=search], input[type=url], select, textarea { 
                background: #333 !important; border-color: #444 !important; color: #e0e0e0 !important; 
            }
            input[type=text]:focus, input[type=password]:focus, input[type=email]:focus, select:focus, textarea:focus { 
                border-color: {$accent_dark} !important; box-shadow: 0 0 0 1px {$accent_dark} !important; 
            }
            
            .button, .button-secondary { background: #333 !important; border-color: #444 !important; color: #e0e0e0 !important; }
            .button:hover, .button-secondary:hover { background: #444 !important; color: #fff !important; }
            .button-primary, .wp-core-ui .button-primary { background: {$accent_dark} !important; border-color: {$accent_dark} !important; color: #fff !important; }
            .button-primary:hover, .wp-core-ui .button-primary:hover { background: " . $this->darken_color( $accent_dark, 10 ) . " !important; border-color: " . $this->darken_color( $accent_dark, 10 ) . " !important; color: #fff !important; }
            
            .notice, .notice-info { background: #252525 !important; border-left-color: {$accent_dark} !important; color: #c0c0c0 !important; }
            .notice-success { border-left-color: #34c759 !important; }
            .notice-warning { border-left-color: #ff9500 !important; }
            .notice-error { border-left-color: #ff3b30 !important; }
            
            .wp-list-table .column-title .row-title { color: {$accent_dark} !important; }
            .wp-list-table .row-actions a { color: {$accent_dark} !important; }
            
            #dashboard-widgets .postbox h2 { color: #e0e0e0 !important; }
            #welcome-panel { background: #252525 !important; border-color: #333 !important; }
            #welcome-panel .welcome-panel-content { color: #c0c0c0 !important; }
            
            .form-table th { color: #e0e0e0 !important; }
            .description, .form-table td p.description { color: #888 !important; }
            
            a, .wp-core-ui a { color: {$accent_dark} !important; }
            a:hover, .wp-core-ui a:hover { color: " . $this->darken_color( $accent_dark, 10 ) . " !important; }
            
            hr { border-color: #333 !important; }
            .card, .wp-editor-container { background: #252525 !important; border-color: #333 !important; }
            
            /* Scrollbar */
            ::-webkit-scrollbar { width: 12px; height: 12px; }
            ::-webkit-scrollbar-track { background: #1a1a1a; }
            ::-webkit-scrollbar-thumb { background: #444; border-radius: 6px; }
            ::-webkit-scrollbar-thumb:hover { background: #555; }
            ";
        }
        
        // Midnight scheme - FULL THEME TRANSFORMATION
        if ( $scheme === 'midnight' ) {
            // Use scheme colors if custom not set
            $midnight_primary = $primary ?: $scheme_primary ?: '#1e1e3f';
            $midnight_secondary = $secondary ?: $scheme_secondary ?: '#2d2d5a';
            $midnight_accent = $accent;
            
            $css .= "
            /* Midnight Theme - Complete Admin Transformation */
            body.wp-admin, html.wp-toolbar { background: #1a1a2e !important; }
            #wpcontent, #wpbody-content { background: #1a1a2e !important; }
            
            /* Sidebar */
            #adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap { 
                background: {$midnight_primary} !important; 
            }
            #adminmenu a { 
                color: #b0b0d0 !important; 
            }
            #adminmenu .wp-submenu { 
                background: {$midnight_secondary} !important; 
            }
            #adminmenu li.menu-top:hover, 
            #adminmenu li.opensub > a.menu-top, 
            #adminmenu li > a.menu-top:focus { 
                background: {$midnight_secondary} !important; 
                color: #fff !important; 
            }
            #adminmenu li.current a.menu-top, 
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu { 
                background: {$midnight_accent} !important; 
                color: #fff !important; 
            }
            
            /* Admin Bar */
            #wpadminbar { 
                background: {$midnight_primary} !important; 
            }
            #wpadminbar .ab-item, #wpadminbar a.ab-item { 
                color: #b0b0d0 !important; 
            }
            #wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item, 
            #wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus { 
                background: {$midnight_secondary} !important; 
                color: #fff !important; 
            }
            
            /* Content Area */
            .wrap h1, .wrap h2, .wrap h3 { color: #e0e0e0 !important; }
            .postbox { 
                background: #252540 !important; 
                border-color: #3a3a5a !important; 
            }
            .postbox .hndle, .postbox .handlediv { 
                color: #d0d0e0 !important; 
                background: #2d2d4a !important;
            }
            .postbox .inside { 
                color: #c0c0d0 !important; 
                background: #252540 !important;
            }
            
            /* Tables */
            .widefat, .widefat td, .widefat th { 
                background: #252540 !important; 
                border-color: #3a3a5a !important; 
                color: #c0c0d0 !important; 
            }
            .widefat thead th, .widefat tfoot th { 
                background: {$midnight_secondary} !important; 
                color: #e0e0e0 !important; 
            }
            .striped > tbody > :nth-child(odd), ul.striped > :nth-child(odd) { 
                background: #2a2a45 !important; 
            }
            
            /* Form Elements */
            input[type=text], input[type=password], input[type=email], 
            input[type=number], input[type=search], input[type=url], 
            select, textarea { 
                background: #2a2a45 !important; 
                border-color: #3a3a5a !important; 
                color: #e0e0e0 !important; 
            }
            input[type=text]:focus, input[type=password]:focus, 
            input[type=email]:focus, select:focus, textarea:focus { 
                border-color: {$midnight_accent} !important; 
                box-shadow: 0 0 0 1px {$midnight_accent} !important; 
            }
            
            /* Buttons */
            .button, .button-secondary { 
                background: #2a2a45 !important; 
                border-color: #3a3a5a !important; 
                color: #e0e0e0 !important; 
            }
            .button:hover, .button-secondary:hover { 
                background: #3a3a5a !important; 
                color: #fff !important; 
            }
            .button-primary, .wp-core-ui .button-primary { 
                background: {$midnight_accent} !important; 
                border-color: {$midnight_accent} !important; 
                color: #fff !important;
            }
            .button-primary:hover, .wp-core-ui .button-primary:hover { 
                background: " . $this->darken_color( $midnight_accent, 10 ) . " !important; 
                border-color: " . $this->darken_color( $midnight_accent, 10 ) . " !important; 
            }
            
            /* Links */
            a, .wp-core-ui a { 
                color: {$midnight_accent} !important; 
            }
            a:hover, .wp-core-ui a:hover { 
                color: " . $this->darken_color( $midnight_accent, 10 ) . " !important; 
            }
            
            /* Checkboxes & Radio */
            input[type=checkbox]:checked, input[type=radio]:checked { 
                accent-color: {$midnight_accent} !important; 
            }
            
            /* Notices */
            .notice, .notice-info { 
                background: #252540 !important; 
                border-left-color: {$midnight_accent} !important; 
                color: #c0c0d0 !important; 
            }
            .notice-success { border-left-color: #34c759 !important; }
            .notice-warning { border-left-color: #ff9500 !important; }
            .notice-error { border-left-color: #ff3b30 !important; }
            
            /* List Tables */
            .wp-list-table .column-title .row-title { 
                color: {$midnight_accent} !important; 
            }
            .wp-list-table .row-actions a { 
                color: {$midnight_accent} !important; 
            }
            
            /* Dashboard */
            #dashboard-widgets .postbox h2 { color: #e0e0e0 !important; }
            #welcome-panel { 
                background: #252540 !important; 
                border-color: #3a3a5a !important; 
            }
            #welcome-panel .welcome-panel-content { color: #c0c0d0 !important; }
            
            /* Forms */
            .form-table th { color: #e0e0e0 !important; }
            .description, .form-table td p.description { color: #888 !important; }
            
            /* Cards & Containers */
            .card, .wp-editor-container { 
                background: #252540 !important; 
                border-color: #3a3a5a !important; 
            }
            hr { border-color: #3a3a5a !important; }
            
            /* Scrollbar */
            ::-webkit-scrollbar { width: 12px; height: 12px; }
            ::-webkit-scrollbar-track { background: {$midnight_primary}; }
            ::-webkit-scrollbar-thumb { background: {$midnight_secondary}; border-radius: 6px; }
            ::-webkit-scrollbar-thumb:hover { background: {$midnight_accent}; }
            ";
        }
        
        // Ocean, Forest, Sunset schemes - COMPLETE THEME TRANSFORMATION
        if ( in_array( $scheme, array( 'ocean', 'forest', 'sunset' ) ) ) {
            // Use scheme colors if custom not set
            $sidebar_bg = $primary ?: $scheme_primary ?: '#23282d';
            $sidebar_hover = $secondary ?: $scheme_secondary ?: '#32373c';
            $scheme_accent = $accent;
            
            // Different background colors for each scheme
            $content_bg = '#f5f5f5';
            $card_bg = '#ffffff';
            $border_color = '#d1d1d6';
            $text_color = '#1d1d1f';
            $text_secondary = '#86868b';
            
            if ( $scheme === 'ocean' ) {
                $content_bg = '#e8f4f8';
                $card_bg = '#f0f8fb';
                $border_color = '#b8d4e0';
            } elseif ( $scheme === 'forest' ) {
                $content_bg = '#e8f5e9';
                $card_bg = '#f0f9f1';
                $border_color = '#b8d4ba';
            } elseif ( $scheme === 'sunset' ) {
                $content_bg = '#fff4e8';
                $card_bg = '#fff9f0';
                $border_color = '#ffd4b8';
            }
            
            $css .= "
            /* {$scheme} Theme - Complete Admin Transformation */
            body.wp-admin, html.wp-toolbar { background: {$content_bg} !important; }
            #wpcontent, #wpbody-content { background: {$content_bg} !important; }
            
            /* Sidebar */
            #adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap { 
                background: {$sidebar_bg} !important; 
            }
            #adminmenu a { 
                color: #b0b0b0 !important; 
            }
            #adminmenu .wp-submenu { 
                background: {$sidebar_hover} !important; 
            }
            #adminmenu li.menu-top:hover, 
            #adminmenu li.opensub > a.menu-top,
            #adminmenu li > a.menu-top:focus { 
                background: {$sidebar_hover} !important; 
                color: #fff !important;
            }
            #adminmenu li.current a.menu-top, 
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,
            #adminmenu li.wp-has-current-submenu .wp-submenu-head { 
                background: {$scheme_accent} !important; 
                color: #fff !important;
            }
            
            /* Admin Bar */
            #wpadminbar { 
                background: {$sidebar_bg} !important; 
            }
            #wpadminbar .ab-item, #wpadminbar a.ab-item { 
                color: #b0b0b0 !important; 
            }
            #wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item, 
            #wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus { 
                background: {$sidebar_hover} !important; 
                color: #fff !important; 
            }
            
            /* Content Area */
            .wrap h1, .wrap h2, .wrap h3 { color: {$text_color} !important; }
            .postbox { 
                background: {$card_bg} !important; 
                border-color: {$border_color} !important; 
            }
            .postbox .hndle, .postbox .handlediv { 
                color: {$text_color} !important; 
                background: {$card_bg} !important;
            }
            .postbox .inside { 
                color: {$text_color} !important; 
                background: {$card_bg} !important;
            }
            
            /* Tables */
            .widefat, .widefat td, .widefat th { 
                background: {$card_bg} !important; 
                border-color: {$border_color} !important; 
                color: {$text_color} !important; 
            }
            .widefat thead th, .widefat tfoot th { 
                background: {$content_bg} !important; 
                color: {$text_color} !important; 
            }
            .striped > tbody > :nth-child(odd), ul.striped > :nth-child(odd) { 
                background: {$content_bg} !important; 
            }
            
            /* Form Elements */
            input[type=text], input[type=password], input[type=email], 
            input[type=number], input[type=search], input[type=url], 
            select, textarea { 
                background: #fff !important; 
                border-color: {$border_color} !important; 
                color: {$text_color} !important; 
            }
            input[type=text]:focus, input[type=password]:focus, 
            input[type=email]:focus, select:focus, textarea:focus { 
                border-color: {$scheme_accent} !important; 
                box-shadow: 0 0 0 1px {$scheme_accent} !important; 
            }
            
            /* Buttons */
            .button, .button-secondary { 
                background: #fff !important; 
                border-color: {$border_color} !important; 
                color: {$text_color} !important; 
            }
            .button:hover, .button-secondary:hover { 
                background: {$content_bg} !important; 
                color: {$text_color} !important; 
            }
            .button-primary, .wp-core-ui .button-primary { 
                background: {$scheme_accent} !important; 
                border-color: {$scheme_accent} !important; 
                color: #fff !important;
            }
            .button-primary:hover, .wp-core-ui .button-primary:hover { 
                background: " . $this->darken_color( $scheme_accent, 10 ) . " !important; 
                border-color: " . $this->darken_color( $scheme_accent, 10 ) . " !important; 
                color: #fff !important;
            }
            
            /* Links */
            a, .wp-core-ui a { 
                color: {$scheme_accent} !important; 
            }
            a:hover, .wp-core-ui a:hover { 
                color: " . $this->darken_color( $scheme_accent, 10 ) . " !important; 
            }
            
            /* Checkboxes & Radio */
            input[type=checkbox]:checked, input[type=radio]:checked { 
                accent-color: {$scheme_accent} !important; 
            }
            
            /* Notices */
            .notice, .notice-info { 
                background: {$card_bg} !important; 
                border-left-color: {$scheme_accent} !important; 
                color: {$text_color} !important; 
            }
            .notice-success { border-left-color: #34c759 !important; }
            .notice-warning { border-left-color: #ff9500 !important; }
            .notice-error { border-left-color: #ff3b30 !important; }
            
            /* List Tables */
            .wp-list-table .column-title .row-title { 
                color: {$scheme_accent} !important; 
            }
            .wp-list-table .row-actions a { 
                color: {$scheme_accent} !important; 
            }
            
            /* Dashboard */
            #dashboard-widgets .postbox h2 { color: {$text_color} !important; }
            #welcome-panel { 
                background: {$card_bg} !important; 
                border-color: {$border_color} !important; 
            }
            #welcome-panel .welcome-panel-content { color: {$text_color} !important; }
            
            /* Forms */
            .form-table th { color: {$text_color} !important; }
            .description, .form-table td p.description { color: {$text_secondary} !important; }
            
            /* Cards & Containers */
            .card, .wp-editor-container { 
                background: {$card_bg} !important; 
                border-color: {$border_color} !important; 
            }
            hr { border-color: {$border_color} !important; }
            
            /* Scrollbar */
            ::-webkit-scrollbar { width: 12px; height: 12px; }
            ::-webkit-scrollbar-track { background: {$content_bg}; }
            ::-webkit-scrollbar-thumb { background: {$scheme_accent}; border-radius: 6px; }
            ::-webkit-scrollbar-thumb:hover { background: " . $this->darken_color( $scheme_accent, 10 ) . "; }
            ";
        }
        
        // Custom accent color for all schemes (including default if custom accent is set)
        if ( ! empty( $accent ) ) {
            // For dark mode, only apply to specific elements
            if ( $scheme === 'dark' ) {
                $css .= "
                #adminmenu li.current a.menu-top, 
                #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu { 
                    background: {$accent} !important; 
                }
                a { color: {$accent} !important; }
                a:hover { color: " . $this->darken_color( $accent, 10 ) . " !important; }
                ";
            } else {
                // For other schemes, apply accent color everywhere
                $css .= "
                #adminmenu li.current a.menu-top, 
                #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,
                #adminmenu li.wp-has-current-submenu .wp-submenu-head { 
                    background: {$accent} !important; 
                    color: #fff !important;
                }
                .button-primary, .wp-core-ui .button-primary, 
                .button.button-primary, input[type=submit].button-primary { 
                    background: {$accent} !important; 
                    border-color: {$accent} !important; 
                    color: #fff !important;
                }
                .button-primary:hover, .wp-core-ui .button-primary:hover,
                .button.button-primary:hover, input[type=submit].button-primary:hover { 
                    background: " . $this->darken_color( $accent, 10 ) . " !important; 
                    border-color: " . $this->darken_color( $accent, 10 ) . " !important; 
                    color: #fff !important;
                }
                a, .wp-core-ui a { color: {$accent} !important; }
                a:hover, .wp-core-ui a:hover { color: " . $this->darken_color( $accent, 10 ) . " !important; }
                input[type=checkbox]:checked { accent-color: {$accent} !important; }
                input[type=radio]:checked { accent-color: {$accent} !important; }
                ";
            }
        }
        
        // Custom admin CSS
        if ( ! empty( $custom['custom_css'] ) ) {
            $css .= "\n/* Custom Admin CSS */\n" . $custom['custom_css'];
        }
        
        return $css;
    }

    /**
     * Darken a hex color
     */
    private function darken_color( $hex, $percent = 10 ) {
        $hex = str_replace( '#', '', $hex );
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        
        $r = max( 0, min( 255, $r - ( $r * $percent / 100 ) ) );
        $g = max( 0, min( 255, $g - ( $g * $percent / 100 ) ) );
        $b = max( 0, min( 255, $b - ( $b * $percent / 100 ) ) );
        
        return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT ) . 
                   str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT ) . 
                   str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
    }

    /**
     * Get available color schemes
     */
    private function get_schemes() {
        return array(
            'default' => array(),
            'dark' => array(
                'primary' => '#0d0d0d',
                'secondary' => '#1a1a1a',
                'accent' => '#007aff',
            ),
            'midnight' => array(
                'primary' => '#1e1e3f',
                'secondary' => '#2d2d5a',
                'accent' => '#6c5ce7',
            ),
            'ocean' => array(
                'primary' => '#23282d',
                'secondary' => '#32373c',
                'accent' => '#0984e3',
            ),
            'forest' => array(
                'primary' => '#23282d',
                'secondary' => '#32373c',
                'accent' => '#00b894',
            ),
            'sunset' => array(
                'primary' => '#23282d',
                'secondary' => '#32373c',
                'accent' => '#e17055',
            ),
        );
    }

    /**
     * Add dark mode toggle to admin bar
     */
    public function add_theme_toggle( $wp_admin_bar ) {
        if ( ! is_admin() ) return;
        
        $user_dark = get_user_meta( get_current_user_id(), 'wac_dark_mode', true );
        $is_dark = $user_dark === 'on' || ( $user_dark === '' && ( $this->options['scheme'] ?? '' ) === 'dark' );
        
        $wp_admin_bar->add_node( array(
            'id'    => 'wac-dark-mode',
            'title' => '<span class="ab-icon dashicons dashicons-' . ( $is_dark ? 'lightbulb' : 'image-filter' ) . '"></span>',
            'href'  => '#',
            'meta'  => array(
                'title' => $is_dark ? 'Switch to Light Mode' : 'Switch to Dark Mode',
                'onclick' => 'wacToggleDarkMode(); return false;',
            ),
        ) );
        
        add_action( 'admin_footer', array( $this, 'dark_mode_script' ) );
    }

    /**
     * Dark mode toggle script
     */
    public function dark_mode_script() {
        ?>
        <script>
        function wacToggleDarkMode() {
            jQuery.post(ajaxurl, {
                action: 'wac_toggle_dark_mode',
                nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
            }, function() {
                location.reload();
            });
        }
        </script>
        <style>
        #wpadminbar #wp-admin-bar-wac-dark-mode .ab-icon { margin-right: 0 !important; }
        #wpadminbar #wp-admin-bar-wac-dark-mode .ab-icon:before { 
            font-family: dashicons; 
            top: 2px;
        }
        </style>
        <?php
    }

    /**
     * AJAX: Toggle dark mode for user
     */
    public function ajax_toggle_dark_mode() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        $current = get_user_meta( get_current_user_id(), 'wac_dark_mode', true );
        $new_value = ( $current === 'on' ) ? 'off' : 'on';
        
        update_user_meta( get_current_user_id(), 'wac_dark_mode', $new_value );
        
        wp_send_json_success( array( 'dark_mode' => $new_value ) );
    }

    /**
     * AJAX: Save theme settings
     */
    public function ajax_save_theme() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $data = isset( $_POST['theme_data'] ) ? json_decode( stripslashes( $_POST['theme_data'] ), true ) : array();
        
        // Validate scheme
        $valid_schemes = array( 'default', 'dark', 'midnight', 'ocean', 'forest', 'sunset' );
        $scheme = sanitize_key( $data['scheme'] ?? 'default' );
        if ( ! in_array( $scheme, $valid_schemes ) ) {
            $scheme = 'default';
        }
        
        $clean = array(
            'scheme'     => $scheme,
            'primary'    => sanitize_hex_color( $data['primary'] ?? '' ),
            'secondary'  => sanitize_hex_color( $data['secondary'] ?? '' ),
            'custom_css' => wp_strip_all_tags( $data['custom_css'] ?? '' ),
        );
        
        // Only save accent if it's not empty (empty means use scheme default)
        $accent = isset( $data['accent'] ) ? trim( $data['accent'] ) : '';
        if ( ! empty( $accent ) && $accent !== '' ) {
            $accent = sanitize_hex_color( $accent );
            if ( ! empty( $accent ) ) {
                $clean['accent'] = $accent;
            }
        }
        // If accent is empty, don't include it (use scheme default)
        
        // Save to database - force update
        update_option( 'wac_admin_theme', $clean, false );
        
        // Refresh instance options immediately
        $this->refresh_options();
        
        // Verify it was saved correctly
        $verify = get_option( 'wac_admin_theme', array() );
        
        wp_send_json_success( array( 
            'message' => 'Theme saved',
            'scheme' => $clean['scheme'],
            'verified_scheme' => $verify['scheme'] ?? 'not found',
            'options' => $clean
        ) );
    }

    /**
     * Render theme settings UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        // Always get fresh options from database
        $instance->refresh_options();
        $options = $instance->options;
        $schemes = array(
            'default'  => 'Default (Light)',
            'dark'     => 'Dark Mode',
            'midnight' => 'Midnight',
            'ocean'    => 'Ocean Blue',
            'forest'   => 'Forest Green',
            'sunset'   => 'Sunset Orange',
        );
        ?>
        <style>
        .wac-theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
        .wac-theme-option{border:2px solid #e5e5ea;border-radius:8px;padding:16px;cursor:pointer;text-align:center;transition:all .2s}
        .wac-theme-option:hover{border-color:#007aff}
        .wac-theme-option.selected{border-color:#007aff;background:#f0f5ff}
        .wac-theme-option input{display:none}
        .wac-theme-preview{height:60px;border-radius:4px;margin-bottom:8px;display:flex;overflow:hidden}
        .wac-theme-preview-sidebar{width:30%;background:#23282d}
        .wac-theme-preview-content{flex:1;background:#f1f1f1}
        .wac-theme-option[data-scheme="dark"] .wac-theme-preview-sidebar{background:#0d0d0d}
        .wac-theme-option[data-scheme="dark"] .wac-theme-preview-content{background:#1a1a1a}
        .wac-theme-option[data-scheme="midnight"] .wac-theme-preview-sidebar{background:#1e1e3f}
        .wac-theme-option[data-scheme="midnight"] .wac-theme-preview-content{background:#2d2d5a}
        .wac-theme-name{font-size:13px;font-weight:500;color:#1d1d1f}
        
        .wac-color-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
        .wac-color-field label{display:block;font-size:12px;font-weight:600;color:#1d1d1f;margin-bottom:6px}
        .wac-color-field input[type=color]{width:100%;height:44px;padding:2px;border:1px solid #d1d1d6;border-radius:6px;cursor:pointer}
        
        .wac-css-field textarea{width:100%;min-height:150px;font-family:monospace;font-size:12px;padding:12px;border:1px solid #d1d1d6;border-radius:6px}
        </style>
        
        <div class="wac-admin-theme">
            <div class="wac-theme-grid">
                <?php foreach ( $schemes as $key => $name ) : ?>
                <label class="wac-theme-option <?php echo ( $options['scheme'] ?? 'default' ) === $key ? 'selected' : ''; ?>" data-scheme="<?php echo esc_attr( $key ); ?>">
                    <input type="radio" name="wac_theme_scheme" value="<?php echo esc_attr( $key ); ?>" <?php checked( $options['scheme'] ?? 'default', $key ); ?>>
                    <div class="wac-theme-preview">
                        <div class="wac-theme-preview-sidebar"></div>
                        <div class="wac-theme-preview-content"></div>
                    </div>
                    <span class="wac-theme-name"><?php echo esc_html( $name ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            
            <h3 style="font-size:14px;margin:0 0 8px">Custom Colors</h3>
            <p style="color:#86868b;font-size:12px;margin:0 0 12px">
                Customize the accent color used throughout the admin area.
            </p>
            <div class="wac-color-grid">
                <div class="wac-color-field">
                    <label>Accent Color</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="color" id="wac-theme-accent" value="<?php echo esc_attr( ! empty( $options['accent'] ) ? $options['accent'] : '' ); ?>" style="flex:1">
                        <button type="button" class="wac-btn wac-btn-secondary" id="wac-clear-accent" style="white-space:nowrap;padding:8px 12px">Clear</button>
                    </div>
                    <?php if ( ! empty( $options['accent'] ) ) : ?>
                        <div style="margin-top:4px">
                            <small style="font-size:11px;color:#86868b">Custom accent is set. Click "Clear" to use scheme default.</small>
                        </div>
                    <?php endif; ?>
                    <small style="display:block;margin-top:4px;font-size:11px;color:#86868b">
                        Applied to: Active menu items, primary buttons, links, checkboxes, and radio buttons. Leave empty to use scheme default.
                    </small>
                </div>
            </div>
            
            <h3 style="font-size:14px;margin:0 0 12px">Custom CSS</h3>
            <div class="wac-css-field">
                <textarea id="wac-theme-css" placeholder="/* Add custom admin CSS here */"><?php echo esc_textarea( $options['custom_css'] ?? '' ); ?></textarea>
            </div>
            
            <div style="margin-top:16px">
                <button type="button" class="wac-btn wac-btn-primary" id="wac-save-theme">Save Theme</button>
                <span id="wac-theme-status" style="margin-left:12px;font-size:12px;color:#86868b"></span>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var accentCleared = false;
            
            // Clear accent color
            $('#wac-clear-accent').on('click', function() {
                accentCleared = true;
                var $input = $('#wac-theme-accent');
                // Remove value completely
                $input.val('').attr('value', '');
                // Show a visual indicator
                var $field = $(this).closest('.wac-color-field');
                var $small = $field.find('small');
                var originalText = $small.data('original') || $small.html();
                if (!$small.data('original')) {
                    $small.data('original', originalText);
                }
                $small.html('<span style="color:#ff9500">Accent cleared. Will use scheme default color.</span>');
            });
            
            // Reset cleared flag when accent color is manually changed
            $('#wac-theme-accent').on('change input', function() {
                var val = $(this).val();
                if (val && val !== '' && val !== '#000000') {
                    accentCleared = false;
                    var $field = $(this).closest('.wac-color-field');
                    var $small = $field.find('small');
                    if ($small.data('original')) {
                        $small.html($small.data('original'));
                    }
                }
            });
            
            // Scheme selection
            $('.wac-theme-option').on('click', function() {
                $('.wac-theme-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input').prop('checked', true);
            });
            
            // Save theme
            $('#wac-save-theme').on('click', function() {
                var $btn = $(this);
                var $status = $('#wac-theme-status');
                
                var accent = $('#wac-theme-accent').val();
                // If user clicked clear button or input is empty/black, send empty string to use scheme default
                if (accentCleared || !accent || accent === '' || accent === '#000000') {
                    accent = '';
                }
                
                var selectedScheme = $('input[name="wac_theme_scheme"]:checked').val();
                if (!selectedScheme) {
                    $status.text('Please select a color scheme').css('color', '#ff3b30');
                    return;
                }
                
                var data = {
                    scheme: selectedScheme,
                    accent: accent,
                    custom_css: $('#wac-theme-css').val()
                };
                
                console.log('Saving theme:', data); // Debug
                
                $btn.text('Saving...').prop('disabled', true);
                $status.text('').css('color', '');
                
                $.post(ajaxurl, {
                    action: 'wac_save_theme',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    theme_data: JSON.stringify(data)
                }, function(res) {
                    console.log('Save response:', res); // Debug
                    if (res.success) {
                        $status.text('Saved! Reloading...').css('color', '#34c759');
                        // Show notification
                        if (typeof wacShowNotification !== 'undefined') {
                            wacShowNotification('Theme saved successfully!', 'success');
                        }
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        $status.text('Error saving: ' + (res.data || 'Unknown error')).css('color', '#ff3b30');
                        if (typeof wacShowNotification !== 'undefined') {
                            wacShowNotification('Error saving theme. Please try again.', 'error');
                        }
                        $btn.text('Save Theme').prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    $status.text('Network error. Please try again.').css('color', '#ff3b30');
                    if (typeof wacShowNotification !== 'undefined') {
                        wacShowNotification('Network error. Please check your connection.', 'error');
                    }
                    $btn.text('Save Theme').prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

