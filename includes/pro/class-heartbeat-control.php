<?php
/**
 * Heartbeat Control
 * Control WordPress Heartbeat API for better performance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Heartbeat_Control {

    private static $instance = null;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'wac_heartbeat', array(
            'dashboard' => 60,
            'editor' => 15,
            'frontend' => 'disable',
        ) );
        
        add_action( 'init', array( $this, 'control_heartbeat' ), 1 );
        add_filter( 'heartbeat_settings', array( $this, 'modify_settings' ) );
        add_action( 'wp_ajax_wac_save_heartbeat', array( $this, 'ajax_save' ) );
    }

    /**
     * Control heartbeat based on location
     */
    public function control_heartbeat() {
        // Disable on frontend
        if ( ! is_admin() && $this->options['frontend'] === 'disable' ) {
            wp_deregister_script( 'heartbeat' );
            return;
        }
    }

    /**
     * Modify heartbeat settings
     */
    public function modify_settings( $settings ) {
        global $pagenow;
        
        // Dashboard
        if ( $pagenow === 'index.php' ) {
            $interval = intval( $this->options['dashboard'] );
            if ( $interval > 0 ) {
                $settings['interval'] = $interval;
            }
        }
        
        // Post editor
        if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
            $interval = intval( $this->options['editor'] );
            if ( $interval > 0 ) {
                $settings['interval'] = $interval;
            }
        }
        
        return $settings;
    }

    /**
     * AJAX: Save settings
     */
    public function ajax_save() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $data = array(
            'dashboard' => isset( $_POST['dashboard'] ) ? intval( $_POST['dashboard'] ) : 60,
            'editor' => isset( $_POST['editor'] ) ? intval( $_POST['editor'] ) : 15,
            'frontend' => isset( $_POST['frontend'] ) ? sanitize_key( $_POST['frontend'] ) : 'disable',
        );
        
        update_option( 'wac_heartbeat', $data );
        
        wp_send_json_success();
    }

    /**
     * Render UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        $options = $instance->options;
        ?>
        <div class="wac-heartbeat">
            <p style="color:#86868b;margin:0 0 16px;font-size:13px">
                The Heartbeat API is used for auto-saving, real-time notifications, and session management. 
                Reducing frequency can improve admin performance, especially on shared hosting.
            </p>
            
            <table class="form-table" style="margin:0">
                <tr>
                    <th style="width:200px;padding:12px 12px 12px 0">Dashboard Interval</th>
                    <td style="padding:12px 0">
                        <select id="wac-heartbeat-dashboard" style="width:150px">
                            <option value="15" <?php selected( $options['dashboard'], 15 ); ?>>15 seconds</option>
                            <option value="30" <?php selected( $options['dashboard'], 30 ); ?>>30 seconds</option>
                            <option value="60" <?php selected( $options['dashboard'], 60 ); ?>>60 seconds (Recommended)</option>
                            <option value="120" <?php selected( $options['dashboard'], 120 ); ?>>2 minutes</option>
                            <option value="300" <?php selected( $options['dashboard'], 300 ); ?>>5 minutes</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th style="padding:12px 12px 12px 0">Post Editor Interval</th>
                    <td style="padding:12px 0">
                        <select id="wac-heartbeat-editor" style="width:150px">
                            <option value="15" <?php selected( $options['editor'], 15 ); ?>>15 seconds (Default)</option>
                            <option value="30" <?php selected( $options['editor'], 30 ); ?>>30 seconds</option>
                            <option value="60" <?php selected( $options['editor'], 60 ); ?>>60 seconds</option>
                            <option value="120" <?php selected( $options['editor'], 120 ); ?>>2 minutes</option>
                        </select>
                        <p class="description" style="margin:4px 0 0;font-size:11px;color:#86868b">Lower values = more frequent auto-saves</p>
                    </td>
                </tr>
                <tr>
                    <th style="padding:12px 12px 12px 0">Frontend</th>
                    <td style="padding:12px 0">
                        <select id="wac-heartbeat-frontend" style="width:150px">
                            <option value="disable" <?php selected( $options['frontend'], 'disable' ); ?>>Disabled (Recommended)</option>
                            <option value="enable" <?php selected( $options['frontend'], 'enable' ); ?>>Enabled</option>
                        </select>
                        <p class="description" style="margin:4px 0 0;font-size:11px;color:#86868b">Disabling on frontend reduces server load</p>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top:16px">
                <button type="button" class="wac-btn wac-btn-primary" id="wac-save-heartbeat">Save Settings</button>
                <span id="wac-heartbeat-status" style="margin-left:12px;font-size:12px;color:#86868b"></span>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            $('#wac-save-heartbeat').on('click', function() {
                var $btn = $(this);
                var $status = $('#wac-heartbeat-status');
                
                $btn.text('Saving...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wac_save_heartbeat',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    dashboard: $('#wac-heartbeat-dashboard').val(),
                    editor: $('#wac-heartbeat-editor').val(),
                    frontend: $('#wac-heartbeat-frontend').val()
                }, function(res) {
                    $btn.text('Save Settings').prop('disabled', false);
                    if (res.success) {
                        $status.text('Saved!').css('color', '#34c759');
                        setTimeout(function() { $status.text(''); }, 3000);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

