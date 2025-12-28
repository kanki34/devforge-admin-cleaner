<?php
/**
 * Export / Import Settings (PRO)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Export_Import {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wac_export_settings', array( $this, 'export_settings' ) );
        add_action( 'wp_ajax_wac_import_settings', array( $this, 'import_settings' ) );
    }

    /**
     * Export settings
     */
    public function export_settings() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $settings = get_option( 'wac_settings', array() );
        
        $export = array(
            'plugin'   => 'webtapot-admin-cleaner',
            'version'  => WAC_VERSION,
            'exported' => current_time( 'mysql' ),
            'settings' => $settings,
        );

        wp_send_json_success( $export );
    }

    /**
     * Import settings
     */
    public function import_settings() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $settings_json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
        $import = json_decode( $settings_json, true );

        if ( ! $import || ! isset( $import['settings'] ) ) {
            wp_send_json_error( 'Invalid import file' );
        }

        if ( ! isset( $import['plugin'] ) || $import['plugin'] !== 'webtapot-admin-cleaner' ) {
            wp_send_json_error( 'This file is not from Webtapot Admin Cleaner' );
        }

        update_option( 'wac_settings', $import['settings'] );

        wp_send_json_success( __( 'Settings imported successfully', 'webtapot-admin-cleaner' ) );
    }

    /**
     * Render Export/Import UI
     */
    public static function render_ui() {
        // UI is now in settings page
    }

    /**
     * Render scripts for export/import
     */
    public static function render_scripts() {
        ?>
        <script>
        jQuery(function($) {
            $('#wac-export-btn').on('click', function() {
                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'wac_export_settings',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                }, function(res) {
                    if (res.success) {
                        var blob = new Blob([JSON.stringify(res.data, null, 2)], {type: 'application/json'});
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'wac-settings.json';
                        a.click();
                    }
                });
            });
            
            $('#wac-import-file').on('change', function() {
                var file = this.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var data = JSON.parse(e.target.result);
                        $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                            action: 'wac_import_settings',
                            nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                            settings: JSON.stringify(data)
                        }, function(res) {
                            if (res.success) {
                                alert('Settings imported!');
                                location.reload();
                            }
                        });
                    } catch(err) {
                        alert('Invalid JSON file');
                    }
                };
                reader.readAsText(file);
            });
        });
        </script>
        <?php
    }
}
