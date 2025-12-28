<?php
/**
 * Activity Log (PRO)
 * Track user activities in admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Activity_Log {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wac_activity_log';
        
        // Create table immediately
        $this->create_table();
        
        // Track activities
        add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'log_logout' ) );
        add_action( 'save_post', array( $this, 'log_post_change' ), 10, 3 );
        add_action( 'delete_post', array( $this, 'log_post_delete' ) );
        add_action( 'activated_plugin', array( $this, 'log_plugin_activation' ) );
        add_action( 'deactivated_plugin', array( $this, 'log_plugin_deactivation' ) );
        add_action( 'user_register', array( $this, 'log_user_register' ) );
        add_action( 'profile_update', array( $this, 'log_profile_update' ) );
        add_action( 'switch_theme', array( $this, 'log_theme_switch' ) );
        add_action( 'update_option', array( $this, 'log_option_update' ), 10, 3 );
        
        // AJAX
        add_action( 'wp_ajax_wac_clear_activity_log', array( $this, 'clear_activity_log' ) );
    }

    /**
     * Create log table
     */
    public function create_table() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
        
        if ( $table_exists ) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            user_name varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_name varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Check if table exists
     */
    private function table_exists() {
        global $wpdb;
        return $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name;
    }

    /**
     * Log an activity
     */
    private function log( $action, $object_type, $object_name ) {
        if ( ! $this->table_exists() ) {
            return;
        }
        
        global $wpdb;
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $user_name = $user->ID ? $user->display_name : 'System';
        
        $wpdb->insert(
            $this->table_name,
            array(
                'user_id'     => $user_id,
                'user_name'   => $user_name,
                'action'      => $action,
                'object_type' => $object_type,
                'object_name' => substr( $object_name, 0, 250 ),
                'ip_address'  => $this->get_ip(),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        // Keep only last 500 entries
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        if ( $count > 500 ) {
            $wpdb->query( "DELETE FROM {$this->table_name} ORDER BY created_at ASC LIMIT 50" );
        }
    }

    /**
     * Get user IP
     */
    private function get_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        }
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' );
    }

    // Logging functions
    public function log_login( $user_login, $user ) {
        $this->log( 'logged in', 'user', $user_login );
    }

    public function log_logout() {
        $user = wp_get_current_user();
        if ( $user->ID ) {
            $this->log( 'logged out', 'user', $user->display_name );
        }
    }

    public function log_post_change( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ) ) ) {
            return;
        }
        
        $action = $update ? 'updated' : 'created';
        $type = $post->post_type === 'post' ? 'post' : ( $post->post_type === 'page' ? 'page' : $post->post_type );
        $this->log( $action, $type, $post->post_title ?: '(no title)' );
    }

    public function log_post_delete( $post_id ) {
        $post = get_post( $post_id );
        if ( $post && ! in_array( $post->post_type, array( 'revision', 'nav_menu_item' ) ) ) {
            $this->log( 'deleted', $post->post_type, $post->post_title ?: '(no title)' );
        }
    }

    public function log_plugin_activation( $plugin ) {
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
        $name = $plugin_data['Name'] ?? $plugin;
        $this->log( 'activated', 'plugin', $name );
    }

    public function log_plugin_deactivation( $plugin ) {
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
        $name = $plugin_data['Name'] ?? $plugin;
        $this->log( 'deactivated', 'plugin', $name );
    }

    public function log_user_register( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            $this->log( 'registered', 'user', $user->display_name );
        }
    }

    public function log_profile_update( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            $this->log( 'updated profile', 'user', $user->display_name );
        }
    }

    public function log_theme_switch( $new_name ) {
        $this->log( 'switched to', 'theme', $new_name );
    }

    public function log_option_update( $option, $old, $new ) {
        // Only log important options
        $important = array( 'blogname', 'blogdescription', 'siteurl', 'home', 'admin_email' );
        if ( in_array( $option, $important ) ) {
            $this->log( 'changed', 'setting', $option );
        }
    }

    /**
     * Clear activity log
     */
    public function clear_activity_log() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        wp_send_json_success();
    }

    /**
     * Get action icon
     */
    public static function get_action_icon( $action ) {
        $icons = array(
            'logged in'       => 'admin-users',
            'logged out'      => 'migrate',
            'created'         => 'plus-alt2',
            'updated'         => 'edit',
            'deleted'         => 'trash',
            'activated'       => 'yes-alt',
            'deactivated'     => 'dismiss',
            'registered'      => 'admin-users',
            'updated profile' => 'id-alt',
            'switched to'     => 'admin-appearance',
            'changed'         => 'admin-settings',
        );
        return $icons[ $action ] ?? 'marker';
    }

    /**
     * Get action color
     */
    public static function get_action_color( $action ) {
        $colors = array(
            'logged in'   => '#34c759',
            'logged out'  => '#8e8e93',
            'created'     => '#007aff',
            'updated'     => '#ff9500',
            'deleted'     => '#ff3b30',
            'activated'   => '#34c759',
            'deactivated' => '#ff9500',
        );
        return $colors[ $action ] ?? '#8e8e93';
    }

    /**
     * Render activity log UI
     */
    public static function render_ui() {
        global $wpdb;
        $instance = self::get_instance();
        $table = $instance->table_name;
        
        // Check table
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
        
        if ( ! $table_exists ) {
            echo '<div style="padding:30px;text-align:center;color:#86868b;background:#f5f5f7;border-radius:8px;">
                <p>Activity log table not found. Please deactivate and reactivate the plugin.</p>
            </div>';
            return;
        }
        
        $logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50" );
        ?>
        
        <style>
        .wac-log-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .wac-log-count{font-size:12px;color:#86868b}
        .wac-log-list{max-height:400px;overflow-y:auto;background:#f5f5f7;border-radius:8px}
        .wac-log-empty{padding:40px;text-align:center;color:#86868b}
        .wac-log-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid #e5e5ea}
        .wac-log-item:last-child{border-bottom:none}
        .wac-log-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .wac-log-icon .dashicons{font-size:16px;width:16px;height:16px;color:#fff}
        .wac-log-content{flex:1;min-width:0}
        .wac-log-text{font-size:13px;color:#1d1d1f;line-height:1.4}
        .wac-log-text strong{font-weight:600}
        .wac-log-meta{font-size:11px;color:#86868b;margin-top:2px}
        </style>
        
        <div class="wac-log-header">
            <span class="wac-log-count"><?php echo count( $logs ); ?> recent activities</span>
            <button type="button" class="button" id="wac-clear-log" <?php echo empty( $logs ) ? 'disabled' : ''; ?>>Clear Log</button>
        </div>
        
        <div class="wac-log-list">
            <?php if ( empty( $logs ) ) : ?>
                <div class="wac-log-empty">
                    <span class="dashicons dashicons-clock" style="font-size:32px;width:32px;height:32px;color:#d1d1d6;margin-bottom:8px;display:block;"></span>
                    No activity recorded yet.<br>
                    <small>Activities will appear here as users interact with your site.</small>
                </div>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : 
                    $color = self::get_action_color( $log->action );
                    $icon = self::get_action_icon( $log->action );
                    $time_diff = human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) );
                ?>
                    <div class="wac-log-item">
                        <div class="wac-log-icon" style="background:<?php echo esc_attr( $color ); ?>">
                            <span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
                        </div>
                        <div class="wac-log-content">
                            <div class="wac-log-text">
                                <strong><?php echo esc_html( $log->user_name ); ?></strong>
                                <?php echo esc_html( $log->action ); ?>
                                <?php echo esc_html( $log->object_type ); ?>
                                "<?php echo esc_html( $log->object_name ); ?>"
                            </div>
                            <div class="wac-log-meta">
                                <?php echo esc_html( $time_diff ); ?> ago
                                <?php if ( $log->ip_address ) : ?>
                                    · <?php echo esc_html( $log->ip_address ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(function($) {
            $('#wac-clear-log').on('click', function() {
                if (!confirm('Clear all activity logs?')) return;
                
                var btn = $(this);
                btn.prop('disabled', true).text('Clearing...');
                
                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'wac_clear_activity_log',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                }, function() {
                    location.reload();
                });
            });
        });
        </script>
        <?php
    }
}
