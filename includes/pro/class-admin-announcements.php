<?php
/**
 * Admin Announcements
 * Show custom announcements on the dashboard for all users
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Admin_Announcements {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ), 1 );
        add_action( 'admin_notices', array( $this, 'show_global_notice' ) );
        add_action( 'wp_ajax_wac_save_announcement', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_wac_delete_announcement', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_wac_dismiss_announcement', array( $this, 'ajax_dismiss' ) );
    }

    /**
     * Add dashboard widget
     */
    public function add_widget() {
        $announcements = get_option( 'wac_announcements', array() );
        $active = array_filter( $announcements, function( $a ) {
            return ! empty( $a['enabled'] ) && $a['type'] === 'widget';
        } );
        
        if ( ! empty( $active ) ) {
            wp_add_dashboard_widget(
                'wac_announcements',
                'Announcements',
                array( $this, 'render_widget' )
            );
            
            // Move to top
            global $wp_meta_boxes;
            $dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
            $widget = array( 'wac_announcements' => $dashboard['wac_announcements'] );
            unset( $dashboard['wac_announcements'] );
            $wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $widget, $dashboard );
        }
    }

    /**
     * Render widget content
     */
    public function render_widget() {
        $announcements = get_option( 'wac_announcements', array() );
        
        echo '<div class="wac-announcements-widget">';
        
        foreach ( $announcements as $id => $ann ) {
            if ( empty( $ann['enabled'] ) || $ann['type'] !== 'widget' ) continue;
            
            // Check if dismissed
            $dismissed = get_user_meta( get_current_user_id(), 'wac_dismissed_' . $id, true );
            if ( $dismissed && empty( $ann['persistent'] ) ) continue;
            
            $style = $this->get_style( $ann['style'] ?? 'info' );
            ?>
            <div class="wac-announcement" style="background:<?php echo $style['bg']; ?>;border-left:4px solid <?php echo $style['border']; ?>;padding:12px 16px;margin-bottom:12px;border-radius:4px;position:relative">
                <strong style="color:<?php echo $style['text']; ?>;display:block;margin-bottom:4px"><?php echo esc_html( $ann['title'] ); ?></strong>
                <div style="color:<?php echo $style['text']; ?>;opacity:.8;font-size:13px"><?php echo wp_kses_post( $ann['content'] ); ?></div>
                <?php if ( empty( $ann['persistent'] ) ) : ?>
                <button type="button" class="wac-dismiss-ann" data-id="<?php echo esc_attr( $id ); ?>" style="position:absolute;top:8px;right:8px;background:none;border:none;cursor:pointer;color:<?php echo $style['text']; ?>;opacity:.5">&times;</button>
                <?php endif; ?>
            </div>
            <?php
        }
        
        echo '</div>';
        ?>
        <script>
        jQuery(function($) {
            $('.wac-dismiss-ann').on('click', function() {
                var id = $(this).data('id');
                $(this).closest('.wac-announcement').fadeOut();
                $.post(ajaxurl, {
                    action: 'wac_dismiss_announcement',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    id: id
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Show global admin notice
     */
    public function show_global_notice() {
        $announcements = get_option( 'wac_announcements', array() );
        
        foreach ( $announcements as $id => $ann ) {
            if ( empty( $ann['enabled'] ) || $ann['type'] !== 'notice' ) continue;
            
            // Check if dismissed
            $dismissed = get_user_meta( get_current_user_id(), 'wac_dismissed_' . $id, true );
            if ( $dismissed && empty( $ann['persistent'] ) ) continue;
            
            $class = 'notice notice-' . ( $ann['style'] ?? 'info' );
            if ( empty( $ann['persistent'] ) ) {
                $class .= ' is-dismissible';
            }
            
            echo '<div class="' . esc_attr( $class ) . '" data-announcement-id="' . esc_attr( $id ) . '">';
            echo '<p><strong>' . esc_html( $ann['title'] ) . '</strong> ' . wp_kses_post( $ann['content'] ) . '</p>';
            echo '</div>';
        }
        
        ?>
        <script>
        jQuery(function($) {
            $(document).on('click', '.notice[data-announcement-id] .notice-dismiss', function() {
                var id = $(this).closest('.notice').data('announcement-id');
                $.post(ajaxurl, {
                    action: 'wac_dismiss_announcement',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    id: id
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get style colors
     */
    private function get_style( $style ) {
        $styles = array(
            'info' => array( 'bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#1565c0' ),
            'success' => array( 'bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#2e7d32' ),
            'warning' => array( 'bg' => '#fff3e0', 'border' => '#ff9800', 'text' => '#e65100' ),
            'error' => array( 'bg' => '#ffebee', 'border' => '#f44336', 'text' => '#c62828' ),
        );
        
        return $styles[ $style ] ?? $styles['info'];
    }

    /**
     * AJAX: Save announcement
     */
    public function ajax_save() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $data = isset( $_POST['announcement'] ) ? json_decode( stripslashes( $_POST['announcement'] ), true ) : array();
        $announcements = get_option( 'wac_announcements', array() );
        
        // If title and content are empty, delete the announcement
        if ( empty( $data['title'] ) && empty( $data['content'] ) && ! empty( $data['id'] ) ) {
            $id = sanitize_key( $data['id'] );
            if ( isset( $announcements[ $id ] ) ) {
                unset( $announcements[ $id ] );
                update_option( 'wac_announcements', $announcements );
                wp_send_json_success( array( 'id' => $id, 'deleted' => true ) );
            }
        }
        
        $id = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : 'ann_' . time();
        
        $announcements[ $id ] = array(
            'title' => sanitize_text_field( $data['title'] ?? '' ),
            'content' => wp_kses_post( $data['content'] ?? '' ),
            'type' => sanitize_key( $data['type'] ?? 'widget' ),
            'style' => sanitize_key( $data['style'] ?? 'info' ),
            'enabled' => ! empty( $data['enabled'] ),
            'persistent' => ! empty( $data['persistent'] ),
        );
        
        update_option( 'wac_announcements', $announcements );
        
        wp_send_json_success( array( 'id' => $id ) );
    }

    /**
     * AJAX: Delete announcement
     */
    public function ajax_delete() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $id = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';
        
        if ( empty( $id ) ) {
            wp_send_json_error( 'Invalid ID' );
        }

        $announcements = get_option( 'wac_announcements', array() );
        
        if ( isset( $announcements[ $id ] ) ) {
            unset( $announcements[ $id ] );
            update_option( 'wac_announcements', $announcements );
            wp_send_json_success( array( 'message' => 'Deleted' ) );
        } else {
            wp_send_json_error( 'Not found' );
        }
    }

    /**
     * AJAX: Dismiss announcement
     */
    public function ajax_dismiss() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        $id = sanitize_key( $_POST['id'] ?? '' );
        if ( $id ) {
            update_user_meta( get_current_user_id(), 'wac_dismissed_' . $id, 1 );
        }
        
        wp_send_json_success();
    }

    /**
     * Render UI
     */
    public static function render_ui() {
        $announcements = get_option( 'wac_announcements', array() );
        ?>
        <style>
        .wac-ann-list{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
        .wac-ann-item{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f9f9fb;border-radius:8px}
        .wac-ann-item:hover{background:#f0f0f5}
        .wac-ann-info strong{font-size:14px;color:#1d1d1f}
        .wac-ann-info small{font-size:11px;color:#86868b;margin-left:8px}
        .wac-ann-status{padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500}
        .wac-ann-status.active{background:#d4edda;color:#155724}
        .wac-ann-status.inactive{background:#e5e5ea;color:#86868b}
        .wac-ann-empty{text-align:center;padding:30px;color:#86868b}
        
        .wac-ann-form{background:#f9f9fb;padding:20px;border-radius:8px;margin-top:16px;display:none}
        .wac-ann-form.open{display:block}
        .wac-ann-row{margin-bottom:16px}
        .wac-ann-row label{display:block;font-size:12px;font-weight:600;color:#1d1d1f;margin-bottom:6px}
        .wac-ann-row input,.wac-ann-row select,.wac-ann-row textarea{width:100%;padding:10px 12px;border:1px solid #d1d1d6;border-radius:6px;font-size:14px}
        .wac-ann-row textarea{min-height:80px;resize:vertical}
        .wac-ann-row-inline{display:flex;gap:16px}
        .wac-ann-row-inline .wac-ann-row{flex:1;margin-bottom:0}
        </style>
        
        <div class="wac-announcements">
            <div class="wac-ann-list" id="wac-ann-list">
                <?php if ( empty( $announcements ) ) : ?>
                <div class="wac-ann-empty">No announcements yet. Click "Add New" to create one.</div>
                <?php else : ?>
                    <?php foreach ( $announcements as $id => $ann ) : ?>
                    <div class="wac-ann-item" data-id="<?php echo esc_attr( $id ); ?>">
                        <div class="wac-ann-info">
                            <strong><?php echo esc_html( $ann['title'] ); ?></strong>
                            <small><?php echo ucfirst( $ann['type'] ); ?></small>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="wac-ann-status <?php echo ! empty( $ann['enabled'] ) ? 'active' : 'inactive'; ?>">
                                <?php echo ! empty( $ann['enabled'] ) ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm wac-edit-ann">Edit</button>
                            <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm wac-delete-ann" style="color:#ff3b30">Delete</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="wac-btn wac-btn-primary" id="wac-add-ann">Add New</button>
            
            <div class="wac-ann-form" id="wac-ann-form">
                <input type="hidden" id="wac-ann-id">
                <div class="wac-ann-row">
                    <label>Title</label>
                    <input type="text" id="wac-ann-title" placeholder="Announcement title">
                </div>
                <div class="wac-ann-row">
                    <label>Content</label>
                    <textarea id="wac-ann-content" placeholder="Announcement message..."></textarea>
                </div>
                <div class="wac-ann-row-inline">
                    <div class="wac-ann-row">
                        <label>Display Type</label>
                        <select id="wac-ann-type">
                            <option value="widget">Dashboard Widget</option>
                            <option value="notice">Admin Notice (Top Bar)</option>
                        </select>
                    </div>
                    <div class="wac-ann-row">
                        <label>Style</label>
                        <select id="wac-ann-style">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Orange)</option>
                            <option value="error">Error (Red)</option>
                        </select>
                    </div>
                </div>
                <div class="wac-ann-row">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="wac-ann-enabled" checked style="width:auto">
                        Enabled
                    </label>
                </div>
                <div class="wac-ann-row">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" id="wac-ann-persistent" style="width:auto">
                        Persistent (Cannot be dismissed)
                    </label>
                </div>
                <div style="display:flex;gap:8px;margin-top:16px">
                    <button type="button" class="wac-btn wac-btn-primary" id="wac-save-ann">Save</button>
                    <button type="button" class="wac-btn wac-btn-secondary" id="wac-cancel-ann">Cancel</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var annData = <?php echo json_encode( $announcements ); ?>;
            
            function showForm(id) {
                var ann = id ? annData[id] : {};
                $('#wac-ann-id').val(id || '');
                $('#wac-ann-title').val(ann.title || '');
                $('#wac-ann-content').val(ann.content || '');
                $('#wac-ann-type').val(ann.type || 'widget');
                $('#wac-ann-style').val(ann.style || 'info');
                $('#wac-ann-enabled').prop('checked', ann.enabled !== false);
                $('#wac-ann-persistent').prop('checked', !!ann.persistent);
                $('#wac-ann-form').addClass('open');
            }
            
            function hideForm() {
                $('#wac-ann-form').removeClass('open');
            }
            
            $('#wac-add-ann').on('click', function() {
                showForm();
            });
            
            $(document).on('click', '.wac-edit-ann', function() {
                var id = $(this).closest('.wac-ann-item').data('id');
                showForm(id);
            });
            
            $(document).on('click', '.wac-delete-ann', function() {
                if (!confirm('Delete this announcement?')) return;
                var $item = $(this).closest('.wac-ann-item');
                var id = $item.data('id');
                delete annData[id];
                // Delete via AJAX
                $.post(ajaxurl, {
                    action: 'wac_delete_announcement',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    id: id
                }, function(res) {
                    if (res.success) {
                        $item.fadeOut(function() { $(this).remove(); });
                        // Check if list is empty
                        if ($('#wac-ann-list .wac-ann-item').length === 0) {
                            location.reload();
                        }
                    } else {
                        alert('Error deleting announcement');
                    }
                });
            });
            
            $('#wac-cancel-ann').on('click', hideForm);
            
            $('#wac-save-ann').on('click', function() {
                var $btn = $(this);
                var data = {
                    id: $('#wac-ann-id').val(),
                    title: $('#wac-ann-title').val(),
                    content: $('#wac-ann-content').val(),
                    type: $('#wac-ann-type').val(),
                    style: $('#wac-ann-style').val(),
                    enabled: $('#wac-ann-enabled').is(':checked'),
                    persistent: $('#wac-ann-persistent').is(':checked')
                };
                
                $btn.text('Saving...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wac_save_announcement',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    announcement: JSON.stringify(data)
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    }
                    $btn.text('Save').prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

