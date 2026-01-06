<?php
/**
 * Dashboard Widgets Cleanup Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Dashboard_Widgets {

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
        
        add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widgets' ), 999 );
        add_action( 'wp_dashboard_setup', array( $this, 'cache_dashboard_widgets' ), 9999 ); // Cache widgets when dashboard loads
        add_action( 'admin_init', array( $this, 'remove_welcome_panel' ) );
        add_action( 'admin_footer', array( $this, 'auto_detect_widgets' ) ); // Auto-detect widgets via JavaScript
        add_action( 'wp_ajax_wac_clear_widget_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_wac_scan_dashboard', array( $this, 'ajax_scan_dashboard' ) );
        add_action( 'wp_ajax_wac_save_detected_widgets', array( $this, 'ajax_save_detected_widgets' ) );
    }
    
    /**
     * Auto-detect widgets via JavaScript on dashboard page
     */
    public function auto_detect_widgets() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'dashboard' ) {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            // Function to detect and save widgets
            function detectAndSaveWidgets() {
                var widgets = [];
                
                // Find all dashboard widgets by their meta box IDs
                $('#dashboard-widgets .postbox').each(function() {
                    var $widget = $(this);
                    var widgetId = $widget.attr('id');
                    
                    if (!widgetId) return;
                    
                    // Extract actual widget ID from dashboard-widget-{id} format
                    var actualId = widgetId.replace(/^dashboard-widget-/, '');
                    
                    // Get title
                    var title = $widget.find('.hndle span').text() || 
                               $widget.find('.hndle').text() || 
                               $widget.find('.hndle .hndle').text() || 
                               '';
                    
                    title = title.trim();
                    
                    // If no title, try to get from widget content
                    if (!title) {
                        title = $widget.find('.inside h3').text() || 
                               $widget.find('.inside h2').text() || 
                               '';
                        title = title.trim();
                    }
                    
                    // Fallback: format ID as title
                    if (!title) {
                        title = actualId.replace(/[-_]/g, ' ');
                        title = title.replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                    }
                    
                    if (actualId && actualId !== '') {
                        widgets.push({
                            id: actualId,
                            title: title || actualId
                        });
                    }
                });
                
                // Check for welcome panel
                if ($('#welcome-panel').length) {
                    widgets.push({
                        id: 'welcome_panel',
                        title: 'Welcome Panel'
                    });
                }
                
                // Also check meta boxes directly (for widgets that might not be in postbox format)
                if (typeof wp !== 'undefined' && wp.metaBoxes && wp.metaBoxes.postboxes) {
                    try {
                        var metaBoxes = wp.metaBoxes.postboxes.getAll();
                        for (var context in metaBoxes) {
                            if (metaBoxes.hasOwnProperty(context)) {
                                for (var priority in metaBoxes[context]) {
                                    if (metaBoxes[context].hasOwnProperty(priority)) {
                                        for (var boxId in metaBoxes[context][priority]) {
                                            if (metaBoxes[context][priority].hasOwnProperty(boxId)) {
                                                var box = metaBoxes[context][priority][boxId];
                                                var exists = false;
                                                for (var i = 0; i < widgets.length; i++) {
                                                    if (widgets[i].id === boxId) {
                                                        exists = true;
                                                        break;
                                                    }
                                                }
                                                if (!exists && boxId && boxId.indexOf('dashboard') !== -1) {
                                                    widgets.push({
                                                        id: boxId,
                                                        title: box.title || boxId
                                                    });
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch(e) {}
                }
                
                // Send to server if we found widgets
                if (widgets.length > 0) {
                    var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wac_save_detected_widgets',
                            nonce: '<?php echo esc_js( wp_create_nonce( 'wac_admin_nonce' ) ); ?>',
                            widgets: JSON.stringify(widgets)
                        },
                        success: function(response) {
                            // Silently cache widgets
                        },
                        error: function() {
                            // Fail silently
                        }
                    });
                }
            }
            
            // Run immediately
            detectAndSaveWidgets();
            
            // Also run after a delay to catch lazy-loaded widgets
            setTimeout(detectAndSaveWidgets, 2000);
            setTimeout(detectAndSaveWidgets, 5000);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Save detected widgets from JavaScript
     */
    public function ajax_save_detected_widgets() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }
        
        $widgets_data = isset( $_POST['widgets'] ) ? json_decode( stripslashes( $_POST['widgets'] ), true ) : array();
        
        if ( empty( $widgets_data ) || ! is_array( $widgets_data ) ) {
            wp_send_json_error( array( 'message' => 'No widgets data' ) );
            return;
        }
        
        $widgets = array();
        
        foreach ( $widgets_data as $widget ) {
            if ( isset( $widget['id'] ) && ! empty( $widget['id'] ) ) {
                $widget_id = sanitize_key( $widget['id'] );
                $title = isset( $widget['title'] ) ? sanitize_text_field( $widget['title'] ) : $widget_id;
                
                // Clean widget ID
                $widget_id = str_replace( 'dashboard-widget-', '', $widget_id );
                $widget_id = trim( $widget_id );
                
                if ( empty( $widget_id ) ) {
                    continue;
                }
                
                // If title is empty or same as ID, create a better title
                if ( empty( $title ) || $title === $widget_id ) {
                    $title = str_replace( array( '_', '-' ), ' ', $widget_id );
                    $title = ucwords( $title );
                }
                
                $widgets[ $widget_id ] = array(
                    'id' => $widget_id,
                    'title' => $title,
                );
            }
        }
        
        // Add default widgets
        $default_widgets = array(
            'welcome_panel' => 'Welcome Panel',
            'dashboard_quick_press' => 'Quick Draft',
            'dashboard_primary' => 'WordPress News',
            'dashboard_right_now' => 'At a Glance',
            'dashboard_activity' => 'Activity',
            'dashboard_site_health' => 'Site Health',
        );
        
        foreach ( $default_widgets as $id => $title ) {
            if ( ! isset( $widgets[ $id ] ) ) {
                $widgets[ $id ] = array(
                    'id' => $id,
                    'title' => $title,
                );
            }
        }
        
        // Cache for 1 hour
        set_transient( 'wac_dashboard_widgets_list', $widgets, HOUR_IN_SECONDS );
        
        wp_send_json_success( array( 
            'widgets' => $widgets,
            'count' => count( $widgets ),
            'message' => sprintf( 'Detected and cached %d widget(s)', count( $widgets ) )
        ) );
    }
    
    /**
     * Cache dashboard widgets when dashboard page loads
     */
    public function cache_dashboard_widgets() {
        global $wp_meta_boxes;
        
        if ( ! isset( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
            return;
        }
        
        $widgets = array();
        
        foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
            if ( ! is_array( $priorities ) ) continue;
            
            foreach ( $priorities as $priority => $boxes ) {
                if ( ! is_array( $boxes ) ) continue;
                
                foreach ( $boxes as $widget_id => $widget_data ) {
                    if ( ! is_array( $widget_data ) ) continue;
                    
                    $title = isset( $widget_data['title'] ) ? $widget_data['title'] : $widget_id;
                    $title = wp_strip_all_tags( $title );
                    
                    if ( empty( $title ) && isset( $widget_data['callback'] ) ) {
                        if ( is_array( $widget_data['callback'] ) && isset( $widget_data['callback'][1] ) ) {
                            $title = $widget_data['callback'][1];
                        } elseif ( is_string( $widget_data['callback'] ) ) {
                            $title = $widget_data['callback'];
                        }
                    }
                    
                    if ( empty( $title ) ) {
                        $title = ucwords( str_replace( array( '_', '-' ), ' ', $widget_id ) );
                    }
                    
                    $widgets[ $widget_id ] = array(
                        'id' => $widget_id,
                        'title' => $title,
                    );
                }
            }
        }
        
        // Add default widgets
        $default_widgets = array(
            'welcome_panel' => 'Welcome Panel',
            'dashboard_quick_press' => 'Quick Draft',
            'dashboard_primary' => 'WordPress News',
            'dashboard_right_now' => 'At a Glance',
            'dashboard_activity' => 'Activity',
            'dashboard_site_health' => 'Site Health',
        );
        
        foreach ( $default_widgets as $id => $title ) {
            if ( ! isset( $widgets[ $id ] ) ) {
                $widgets[ $id ] = array(
                    'id' => $id,
                    'title' => $title,
                );
            }
        }
        
        // Cache for 1 hour
        if ( ! empty( $widgets ) ) {
            set_transient( 'wac_dashboard_widgets_list', $widgets, HOUR_IN_SECONDS );
        }
    }
    
    /**
     * AJAX: Clear widget cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        delete_transient( 'wac_dashboard_widgets_list' );
        
        wp_send_json_success( array( 'message' => 'Cache cleared' ) );
    }
    
    /**
     * AJAX: Scan dashboard for widgets
     */
    public function ajax_scan_dashboard() {
        // Check nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wac_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }
        
        // Try to get from cache first
        $widgets = get_transient( 'wac_dashboard_widgets_list' );
        
        if ( $widgets === false || empty( $widgets ) ) {
            // No cache, try to get from current state
            global $wp_meta_boxes;
            $widgets = array();
            
            if ( isset( $wp_meta_boxes['dashboard'] ) && is_array( $wp_meta_boxes['dashboard'] ) && ! empty( $wp_meta_boxes['dashboard'] ) ) {
                foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
                    if ( ! is_array( $priorities ) ) continue;
                    
                    foreach ( $priorities as $priority => $boxes ) {
                        if ( ! is_array( $boxes ) ) continue;
                        
                        foreach ( $boxes as $widget_id => $widget_data ) {
                            if ( ! is_array( $widget_data ) ) continue;
                            
                            $title = isset( $widget_data['title'] ) ? $widget_data['title'] : $widget_id;
                            $title = wp_strip_all_tags( $title );
                            
                            if ( empty( $title ) && isset( $widget_data['callback'] ) ) {
                                if ( is_array( $widget_data['callback'] ) && isset( $widget_data['callback'][1] ) ) {
                                    $title = $widget_data['callback'][1];
                                } elseif ( is_string( $widget_data['callback'] ) ) {
                                    $title = $widget_data['callback'];
                                }
                            }
                            
                            if ( empty( $title ) ) {
                                $title = ucwords( str_replace( array( '_', '-' ), ' ', $widget_id ) );
                            }
                            
                            $widgets[ $widget_id ] = array(
                                'id' => $widget_id,
                                'title' => $title,
                            );
                        }
                    }
                }
            }
        }
        
        // Add default widgets
        $default_widgets = array(
            'welcome_panel' => 'Welcome Panel',
            'dashboard_quick_press' => 'Quick Draft',
            'dashboard_primary' => 'WordPress News',
            'dashboard_right_now' => 'At a Glance',
            'dashboard_activity' => 'Activity',
            'dashboard_site_health' => 'Site Health',
        );
        
        foreach ( $default_widgets as $id => $title ) {
            if ( ! isset( $widgets[ $id ] ) ) {
                $widgets[ $id ] = array(
                    'id' => $id,
                    'title' => $title,
                );
            }
        }
        
        // Cache for 1 hour
        set_transient( 'wac_dashboard_widgets_list', $widgets, HOUR_IN_SECONDS );
        
        $message = sprintf( 'Found %d widget(s)', count( $widgets ) );
        $note = '';
        
        // Check if we only have default widgets
        if ( count( $widgets ) <= count( $default_widgets ) ) {
            $note = 'Only default widgets found. Visit the Dashboard page to load plugin/widget widgets (e.g., Elementor), then return here.';
        }
        
        wp_send_json_success( array( 
            'widgets' => $widgets,
            'count' => count( $widgets ),
            'message' => $message,
            'note' => $note
        ) );
    }

    /**
     * Get all registered dashboard widgets
     */
    public static function get_all_widgets() {
        // Only run on admin settings page
        if ( ! is_admin() || ! isset( $_GET['page'] ) || $_GET['page'] !== 'admin-toolkit' ) {
            return array();
        }
        
        // Try to get from transient first (cached)
        $widgets = get_transient( 'wac_dashboard_widgets_list' );
        if ( $widgets === false || ! is_array( $widgets ) ) {
            $widgets = array();
        }
        
        // CRITICAL: Get custom widgets from database
        $options = get_option( 'wac_settings', array() );
        $custom_widgets = isset( $options['custom_dashboard_widgets'] ) ? $options['custom_dashboard_widgets'] : array();
        
        // CRITICAL: Remove custom widgets from $widgets array (they should only appear in Custom Widgets section)
        // Normalize custom widget IDs for case-insensitive comparison
        $custom_ids_normalized = array();
        if ( ! empty( $custom_widgets ) && is_array( $custom_widgets ) ) {
            foreach ( $custom_widgets as $custom_id ) {
                if ( ! empty( $custom_id ) ) {
                    $custom_ids_normalized[ strtolower( trim( $custom_id ) ) ] = $custom_id;
                }
            }
        }
        
        // Remove custom widgets from $widgets array
        foreach ( $widgets as $widget_id => $widget_data ) {
            $widget_id_normalized = strtolower( trim( $widget_id ) );
            if ( isset( $custom_ids_normalized[ $widget_id_normalized ] ) ) {
                unset( $widgets[ $widget_id ] );
            }
        }
        
        // If no widgets from cache, try to get from current state
        if ( empty( $widgets ) || count( $widgets ) <= count( $custom_widgets ) ) {
            global $wp_meta_boxes;
            
            // Get currently registered widgets (if dashboard was already loaded)
            if ( isset( $wp_meta_boxes['dashboard'] ) && is_array( $wp_meta_boxes['dashboard'] ) ) {
                foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
                    if ( ! is_array( $priorities ) ) continue;
                    
                    foreach ( $priorities as $priority => $boxes ) {
                        if ( ! is_array( $boxes ) ) continue;
                        
                        foreach ( $boxes as $widget_id => $widget_data ) {
                            if ( ! is_array( $widget_data ) ) continue;
                            
                            // Skip if this is a custom widget (case-insensitive check)
                            $widget_id_normalized = strtolower( trim( $widget_id ) );
                            if ( isset( $custom_ids_normalized[ $widget_id_normalized ] ) ) {
                                continue;
                            }
                            
                            // Skip if already in widgets array
                            if ( isset( $widgets[ $widget_id ] ) ) {
                                continue;
                            }
                            
                            $title = isset( $widget_data['title'] ) ? $widget_data['title'] : $widget_id;
                            $title = wp_strip_all_tags( $title );
                            
                            if ( empty( $title ) && isset( $widget_data['callback'] ) ) {
                                if ( is_array( $widget_data['callback'] ) && isset( $widget_data['callback'][1] ) ) {
                                    $title = $widget_data['callback'][1];
                                } elseif ( is_string( $widget_data['callback'] ) ) {
                                    $title = $widget_data['callback'];
                                }
                            }
                            
                            if ( empty( $title ) ) {
                                $title = ucwords( str_replace( array( '_', '-' ), ' ', $widget_id ) );
                            }
                            
                            $widgets[ $widget_id ] = array(
                                'id' => $widget_id,
                                'title' => $title,
                                'context' => $context,
                                'priority' => $priority,
                            );
                        }
                    }
                }
            }
        }
        
        // Add default widgets
        $default_widgets = array(
            'welcome_panel' => 'Welcome Panel',
            'dashboard_quick_press' => 'Quick Draft',
            'dashboard_primary' => 'WordPress News',
            'dashboard_right_now' => 'At a Glance',
            'dashboard_activity' => 'Activity',
            'dashboard_site_health' => 'Site Health',
        );
        
        foreach ( $default_widgets as $id => $title ) {
            // Skip if this is a custom widget
            $id_normalized = strtolower( trim( $id ) );
            if ( isset( $custom_ids_normalized[ $id_normalized ] ) ) {
                continue;
            }
            
            if ( ! isset( $widgets[ $id ] ) ) {
                $widgets[ $id ] = array(
                    'id' => $id,
                    'title' => $title,
                    'context' => 'normal',
                    'priority' => 'core',
                );
            }
        }
        
        // Final pass: Remove any custom widgets that might have been added (double-check)
        // This is CRITICAL to ensure custom widgets never appear in plugin/theme/core sections
        foreach ( $widgets as $widget_id => $widget_data ) {
            $widget_id_normalized = strtolower( trim( $widget_id ) );
            if ( isset( $custom_ids_normalized[ $widget_id_normalized ] ) ) {
                unset( $widgets[ $widget_id ] );
            }
        }
        
        // Sort by title
        uasort( $widgets, function( $a, $b ) {
            return strcasecmp( $a['title'], $b['title'] );
        } );
        
        // Cache for 1 hour (but don't overwrite if we only have custom widgets)
        if ( count( $widgets ) > count( $custom_widgets ) ) {
            set_transient( 'wac_dashboard_widgets_list', $widgets, HOUR_IN_SECONDS );
        }
        
        return $widgets;
    }

    /**
     * Remove dashboard widgets
     */
    public function remove_dashboard_widgets() {
        $hidden_widgets = isset( $this->options['hide_dashboard_widgets'] ) ? $this->options['hide_dashboard_widgets'] : array();
        
        if ( empty( $hidden_widgets ) ) {
            return;
        }

        // Get all registered widgets to find their context
        global $wp_meta_boxes;
        
        // Try all possible contexts
        $contexts = array( 'normal', 'side', 'column3', 'column4' );
        
        foreach ( $hidden_widgets as $widget_id ) {
            // Special handling for welcome panel
            if ( $widget_id === 'welcome_panel' ) {
                continue; // Handled separately
            }
            
            // Try to remove from all contexts
            foreach ( $contexts as $context ) {
                remove_meta_box( $widget_id, 'dashboard', $context );
            }
        }
    }

    /**
     * Remove welcome panel
     */
    public function remove_welcome_panel() {
        $hidden_widgets = isset( $this->options['hide_dashboard_widgets'] ) ? $this->options['hide_dashboard_widgets'] : array();
        
        if ( in_array( 'welcome_panel', $hidden_widgets ) ) {
            remove_action( 'welcome_panel', 'wp_welcome_panel' );
            
            // Also update user meta to hide it
            $user_id = get_current_user_id();
            if ( $user_id && get_user_meta( $user_id, 'show_welcome_panel', true ) == 1 ) {
                update_user_meta( $user_id, 'show_welcome_panel', 0 );
            }
        }
    }
}

