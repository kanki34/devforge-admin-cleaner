<?php
/**
 * Admin Cleaner Class
 * Handles admin bar and general cleanup tasks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Admin_Cleaner {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin bar cleanup
        add_action( 'wp_before_admin_bar_render', array( $this, 'hide_admin_bar_items' ), 999 );
        
        // Hide admin bar completely in wp-admin
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_in_admin' ) );
        
        // Hide admin bar completely on frontend (no space left)
        // Use priority 1 to run as early as possible
        add_action( 'wp_head', array( $this, 'hide_admin_bar_frontend' ), 1 );
        add_action( 'admin_head', array( $this, 'hide_admin_bar_admin' ), 1 );
        
        // Remove admin bar margin/padding via inline styles (highest priority)
        add_action( 'wp_footer', array( $this, 'remove_admin_bar_spacing_footer' ), 999 );
        add_action( 'admin_footer', array( $this, 'remove_admin_bar_spacing_footer' ), 999 );
        
        // Screen options and help tab
        add_action( 'admin_head', array( $this, 'hide_screen_options' ) );
        add_action( 'admin_head', array( $this, 'hide_help_tab' ) );
    }

    /**
     * Hide admin bar items
     */
    public function hide_admin_bar_items() {
        global $wp_admin_bar;
        
        $options = get_option( 'wac_settings', array() );
        $hidden_bar = isset( $options['hide_admin_bar_items'] ) ? $options['hide_admin_bar_items'] : array();
        
        if ( empty( $hidden_bar ) || ! is_array( $hidden_bar ) ) {
            return;
        }

        foreach ( $hidden_bar as $bar_id ) {
            $wp_admin_bar->remove_node( $bar_id );
        }
    }

    /**
     * Hide screen options
     */
    public function hide_screen_options() {
        $options = get_option( 'wac_settings', array() );
        
        if ( empty( $options['hide_screen_options'] ) ) {
            return;
        }

        echo '<style>#screen-options-link-wrap { display: none !important; }</style>';
    }

    /**
     * Hide help tab
     */
    public function hide_help_tab() {
        $options = get_option( 'wac_settings', array() );
        
        if ( empty( $options['hide_help_tab'] ) ) {
            return;
        }

        echo '<style>#contextual-help-link-wrap { display: none !important; }</style>';
    }

    /**
     * Hide admin bar completely in wp-admin
     * IMPORTANT: This only works in wp-admin, NOT on frontend
     */
    public function hide_admin_bar_in_admin( $show ) {
        // CRITICAL: Only hide in wp-admin, never on frontend
        if ( ! is_admin() ) {
            return $show;
        }
        
        $options = get_option( 'wac_settings', array() );
        
        // Only hide if the wp-admin specific option is enabled
        // Frontend option should NOT affect wp-admin
        if ( ! empty( $options['hide_admin_bar_in_admin'] ) ) {
            return false;
        }
        
        return $show;
    }

    /**
     * Hide admin bar on frontend (no space left, content starts from top)
     * IMPORTANT: This only works on frontend, NOT in wp-admin
     */
    public function hide_admin_bar_frontend() {
        // CRITICAL: Only hide on frontend, never in wp-admin
        if ( is_admin() ) {
            return;
        }
        
        $options = get_option( 'wac_settings', array() );
        
        if ( ! empty( $options['hide_admin_bar_frontend'] ) ) {
            // Remove admin bar completely - no space left
            // Remove all possible margins and paddings that WordPress adds for admin bar
            echo '<style id="wac-hide-admin-bar-frontend">
                #wpadminbar { 
                    display: none !important; 
                    height: 0 !important;
                    min-height: 0 !important;
                    visibility: hidden !important;
                }
                html { 
                    margin-top: 0 !important; 
                }
                html[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                body.admin-bar { 
                    padding-top: 0 !important; 
                    margin-top: 0 !important;
                }
                body[style*="padding-top"] {
                    padding-top: 0 !important;
                }
                body[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                * html body { 
                    margin-top: 0 !important; 
                }
                body {
                    margin-top: 0 !important;
                    padding-top: 0 !important;
                }
            </style>';
        }
    }

    /**
     * Hide admin bar in wp-admin (no space left)
     */
    public function hide_admin_bar_admin() {
        if ( ! is_admin() ) {
            return;
        }
        
        $options = get_option( 'wac_settings', array() );
        
        if ( ! empty( $options['hide_admin_bar_in_admin'] ) ) {
            // Remove admin bar completely - no space left
            // Remove all possible margins and paddings that WordPress adds for admin bar
            echo '<style id="wac-hide-admin-bar">
                #wpadminbar { 
                    display: none !important; 
                    height: 0 !important;
                    min-height: 0 !important;
                    max-height: 0 !important;
                    visibility: hidden !important;
                    position: absolute !important;
                    top: -9999px !important;
                }
                html { 
                    margin-top: 0 !important; 
                    padding-top: 0 !important;
                }
                html[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                body { 
                    padding-top: 0 !important; 
                    margin-top: 0 !important;
                }
                body.admin-bar { 
                    padding-top: 0 !important; 
                    margin-top: 0 !important;
                }
                body[style*="padding-top"] {
                    padding-top: 0 !important;
                }
                body[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                * html body { 
                    margin-top: 0 !important; 
                }
                #wpbody { 
                    margin-top: 0 !important; 
                    padding-top: 0 !important;
                }
                #wpbody[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                #wpbody[style*="padding-top"] {
                    padding-top: 0 !important;
                }
                #wpcontent { 
                    padding-top: 0 !important; 
                    margin-top: 0 !important;
                }
                #wpcontent[style*="padding-top"] {
                    padding-top: 0 !important;
                }
                #wpcontent[style*="margin-top"] {
                    margin-top: 0 !important;
                }
                #wpbody-content { 
                    padding-top: 0 !important; 
                    margin-top: 0 !important;
                }
                .wp-toolbar #wpcontent {
                    padding-top: 0 !important;
                    margin-top: 0 !important;
                }
                .wp-toolbar #wpbody {
                    padding-top: 0 !important;
                    margin-top: 0 !important;
                }
                .wp-toolbar {
                    padding-top: 0 !important;
                    margin-top: 0 !important;
                }
            </style>';
        }
    }
    
    /**
     * Remove admin bar spacing via JavaScript (runs after all styles)
     */
    public function remove_admin_bar_spacing_footer() {
        $options = get_option( 'wac_settings', array() );
        
        // Determine which option is active and where we are
        $hide_in_admin = ! empty( $options['hide_admin_bar_in_admin'] );
        $hide_on_frontend = ! empty( $options['hide_admin_bar_frontend'] );
        $is_admin_page = is_admin();
        
        // Only apply frontend hiding on frontend, never in wp-admin
        if ( $is_admin_page && $hide_on_frontend && ! $hide_in_admin ) {
            return; // Don't hide admin bar in wp-admin if only frontend option is enabled
        }
        
        // Only apply admin hiding in wp-admin, never on frontend
        if ( ! $is_admin_page && $hide_in_admin && ! $hide_on_frontend ) {
            return; // Don't hide admin bar on frontend if only wp-admin option is enabled
        }
        
        if ( $hide_in_admin || $hide_on_frontend ) {
            // Use JavaScript to forcefully remove any inline styles WordPress might add
            $is_admin_js = $is_admin_page ? 'true' : 'false';
            $hide_admin_js = $hide_in_admin ? 'true' : 'false';
            $hide_frontend_js = $hide_on_frontend ? 'true' : 'false';
            ?>
            <script>
            (function() {
                var hasRun = false;
                var isAdminPage = <?php echo wp_json_encode( $is_admin_js ); ?>;
                var hideInAdmin = <?php echo wp_json_encode( $hide_admin_js ); ?>;
                var hideOnFrontend = <?php echo wp_json_encode( $hide_frontend_js ); ?>;
                
                function removeAdminBarSpacing() {
                    try {
                        // Check if we should hide based on current page
                        if (isAdminPage && !hideInAdmin) {
                            return; // Do not hide in wp-admin if only frontend option is enabled
                        }
                        if (!isAdminPage && !hideOnFrontend) {
                            return; // Do not hide on frontend if only wp-admin option is enabled
                        }
                        
                        // Remove admin bar completely
                        var adminBar = document.getElementById("wpadminbar");
                        if (adminBar) {
                            adminBar.style.setProperty("display", "none", "important");
                            adminBar.style.setProperty("height", "0", "important");
                            adminBar.style.setProperty("min-height", "0", "important");
                            adminBar.style.setProperty("max-height", "0", "important");
                            adminBar.style.setProperty("visibility", "hidden", "important");
                            adminBar.style.setProperty("position", "absolute", "important");
                            adminBar.style.setProperty("top", "-9999px", "important");
                            adminBar.remove();
                        }
                        
                        // Remove margin-top from html
                        var html = document.documentElement;
                        if (html) {
                            html.style.setProperty("margin-top", "0", "important");
                            html.style.setProperty("padding-top", "0", "important");
                            // Remove inline style attribute if it exists
                            if (html.getAttribute("style") && html.getAttribute("style").indexOf("margin-top") !== -1) {
                                var currentStyle = html.getAttribute("style");
                                currentStyle = currentStyle.replace(/margin-top\s*:\s*[^;]+;?/gi, "");
                                html.setAttribute("style", currentStyle);
                                html.style.setProperty("margin-top", "0", "important");
                            }
                        }
                        
                        // Remove padding-top and margin-top from body
                        var body = document.body;
                        if (body) {
                            body.style.setProperty("padding-top", "0", "important");
                            body.style.setProperty("margin-top", "0", "important");
                            body.classList.remove("admin-bar");
                            // Remove inline style attribute if it exists
                            if (body.getAttribute("style") && (body.getAttribute("style").indexOf("padding-top") !== -1 || body.getAttribute("style").indexOf("margin-top") !== -1)) {
                                var currentStyle = body.getAttribute("style");
                                currentStyle = currentStyle.replace(/padding-top\s*:\s*[^;]+;?/gi, "");
                                currentStyle = currentStyle.replace(/margin-top\s*:\s*[^;]+;?/gi, "");
                                body.setAttribute("style", currentStyle);
                                body.style.setProperty("padding-top", "0", "important");
                                body.style.setProperty("margin-top", "0", "important");
                            }
                        }
                        
                        // Remove spacing from wp-admin elements (only in admin)
                        if (isAdminPage && document.getElementById("wpbody")) {
                            var wpbody = document.getElementById("wpbody");
                            if (wpbody) {
                                wpbody.style.setProperty("margin-top", "0", "important");
                                wpbody.style.setProperty("padding-top", "0", "important");
                            }
                            
                            var wpcontent = document.getElementById("wpcontent");
                            if (wpcontent) {
                                wpcontent.style.setProperty("padding-top", "0", "important");
                                wpcontent.style.setProperty("margin-top", "0", "important");
                            }
                            
                            var wpbodyContent = document.getElementById("wpbody-content");
                            if (wpbodyContent) {
                                wpbodyContent.style.setProperty("padding-top", "0", "important");
                                wpbodyContent.style.setProperty("margin-top", "0", "important");
                            }
                        }
                    } catch(e) {
                        console.error("WAC Admin Bar removal error:", e);
                    }
                }
                
                // Run after DOM is ready
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        removeAdminBarSpacing();
                        hasRun = true;
                    });
                } else {
                    removeAdminBarSpacing();
                    hasRun = true;
                }
                
                // Run after page load
                window.addEventListener("load", function() {
                    removeAdminBarSpacing();
                });
                
                // Run after a short delay
                setTimeout(function() {
                    if (!hasRun) {
                        removeAdminBarSpacing();
                    }
                }, 100);
            })();
            </script>
            <?php
        }
    }
}
