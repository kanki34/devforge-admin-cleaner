<?php
/**
 * Dashboard Widget Builder
 * Create custom dashboard widgets with various content types
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Dashboard_Builder {

    private static $instance = null;
    private $widgets;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->widgets = get_option( 'wac_custom_widgets', array() );
        
        add_action( 'wp_dashboard_setup', array( $this, 'register_widgets' ), 5 );
        add_action( 'wp_ajax_wac_save_widget', array( $this, 'ajax_save_widget' ) );
        add_action( 'wp_ajax_wac_delete_widget', array( $this, 'ajax_delete_widget' ) );
        add_action( 'wp_ajax_wac_get_widgets', array( $this, 'ajax_get_widgets' ) );
    }

    /**
     * Register custom widgets on dashboard
     */
    public function register_widgets() {
        if ( empty( $this->widgets ) || ! is_array( $this->widgets ) ) {
            return;
        }

        foreach ( $this->widgets as $widget_id => $widget ) {
            if ( empty( $widget['enabled'] ) ) {
                continue;
            }

            // Check role restrictions
            if ( ! empty( $widget['roles'] ) && is_array( $widget['roles'] ) ) {
                $user = wp_get_current_user();
                $has_access = false;
                foreach ( $user->roles as $role ) {
                    if ( in_array( $role, $widget['roles'] ) ) {
                        $has_access = true;
                        break;
                    }
                }
                if ( ! $has_access ) {
                    continue;
                }
            }

            wp_add_dashboard_widget(
                'wac_widget_' . $widget_id,
                esc_html( $widget['title'] ),
                function() use ( $widget ) {
                    $this->render_widget_content( $widget );
                }
            );
        }
    }

    /**
     * Render widget content based on type
     */
    private function render_widget_content( $widget ) {
        $type = $widget['type'] ?? 'text';
        $content = $widget['content'] ?? '';

        echo '<div class="wac-custom-widget">';
        
        switch ( $type ) {
            case 'text':
                echo wp_kses_post( wpautop( $content ) );
                break;

            case 'html':
                echo wp_kses_post( $content );
                break;

            case 'rss':
                if ( ! empty( $content ) ) {
                    $rss = fetch_feed( $content );
                    if ( ! is_wp_error( $rss ) ) {
                        $items = $rss->get_items( 0, 5 );
                        if ( ! empty( $items ) ) {
                            echo '<ul style="margin:0;padding:0;list-style:none">';
                            foreach ( $items as $item ) {
                                printf(
                                    '<li style="padding:8px 0;border-bottom:1px solid #eee"><a href="%s" target="_blank" style="text-decoration:none">%s</a><br><small style="color:#999">%s</small></li>',
                                    esc_url( $item->get_permalink() ),
                                    esc_html( $item->get_title() ),
                                    human_time_diff( $item->get_date( 'U' ) ) . ' ago'
                                );
                            }
                            echo '</ul>';
                        }
                    } else {
                        echo '<p style="color:#999">Unable to load RSS feed.</p>';
                    }
                }
                break;

            case 'stats':
                $this->render_stats_widget( $widget );
                break;

            case 'shortcuts':
                $this->render_shortcuts_widget( $widget );
                break;

            case 'notes':
                $this->render_notes_widget( $widget );
                break;
        }

        echo '</div>';
    }

    /**
     * Render quick stats widget
     */
    private function render_stats_widget( $widget ) {
        $stats = array(
            'posts'    => wp_count_posts()->publish,
            'pages'    => wp_count_posts( 'page' )->publish,
            'comments' => wp_count_comments()->approved,
            'users'    => count_users()['total_users'],
        );

        if ( class_exists( 'WooCommerce' ) ) {
            $stats['orders'] = wc_orders_count( 'completed' );
            $stats['products'] = wp_count_posts( 'product' )->publish;
        }

        echo '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">';
        foreach ( $stats as $key => $value ) {
            $label = ucfirst( $key );
            printf(
                '<div style="background:#f9f9f9;padding:16px;border-radius:6px;text-align:center">
                    <div style="font-size:28px;font-weight:600;color:#1d1d1f">%s</div>
                    <div style="font-size:12px;color:#86868b;text-transform:uppercase">%s</div>
                </div>',
                number_format_i18n( $value ),
                esc_html( $label )
            );
        }
        echo '</div>';
    }

    /**
     * Render shortcuts widget
     */
    private function render_shortcuts_widget( $widget ) {
        $shortcuts = ! empty( $widget['shortcuts'] ) ? $widget['shortcuts'] : array();
        
        if ( empty( $shortcuts ) ) {
            $shortcuts = array(
                array( 'label' => 'New Post', 'url' => admin_url( 'post-new.php' ) ),
                array( 'label' => 'New Page', 'url' => admin_url( 'post-new.php?post_type=page' ) ),
                array( 'label' => 'Upload Media', 'url' => admin_url( 'media-new.php' ) ),
                array( 'label' => 'Plugins', 'url' => admin_url( 'plugins.php' ) ),
            );
        }

        echo '<div style="display:flex;flex-wrap:wrap;gap:8px">';
        foreach ( $shortcuts as $shortcut ) {
            printf(
                '<a href="%s" style="display:inline-block;padding:8px 16px;background:#007aff;color:#fff;border-radius:6px;text-decoration:none;font-size:13px">%s</a>',
                esc_url( $shortcut['url'] ),
                esc_html( $shortcut['label'] )
            );
        }
        echo '</div>';
    }

    /**
     * Render notes widget
     */
    private function render_notes_widget( $widget ) {
        $user_id = get_current_user_id();
        $notes = get_user_meta( $user_id, 'wac_dashboard_notes', true );
        ?>
        <div class="wac-notes-widget">
            <textarea id="wac-user-notes" style="width:100%;height:120px;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-size:13px" placeholder="Write your notes here..."><?php echo esc_textarea( $notes ); ?></textarea>
            <button type="button" id="wac-save-notes" style="margin-top:8px;padding:8px 16px;background:#1d1d1f;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px">Save Notes</button>
        </div>
        <script>
        jQuery(function($) {
            $('#wac-save-notes').on('click', function() {
                var $btn = $(this);
                var notes = $('#wac-user-notes').val();
                
                $btn.text('Saving...');
                
                $.post(ajaxurl, {
                    action: 'wac_save_user_notes',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    notes: notes
                }, function() {
                    $btn.text('Saved!');
                    setTimeout(function() {
                        $btn.text('Save Notes');
                    }, 2000);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Save widget
     */
    public function ajax_save_widget() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $widget_id = isset( $_POST['widget_id'] ) ? sanitize_key( $_POST['widget_id'] ) : '';
        $widget_data = isset( $_POST['widget_data'] ) ? json_decode( stripslashes( $_POST['widget_data'] ), true ) : array();

        if ( empty( $widget_id ) || ! is_array( $widget_data ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        // Sanitize
        $clean = array(
            'title'    => sanitize_text_field( $widget_data['title'] ?? 'Custom Widget' ),
            'type'     => sanitize_key( $widget_data['type'] ?? 'text' ),
            'content'  => wp_kses_post( $widget_data['content'] ?? '' ),
            'enabled'  => ! empty( $widget_data['enabled'] ),
            'roles'    => isset( $widget_data['roles'] ) && is_array( $widget_data['roles'] ) 
                ? array_map( 'sanitize_key', $widget_data['roles'] ) : array(),
        );

        if ( ! empty( $widget_data['shortcuts'] ) && is_array( $widget_data['shortcuts'] ) ) {
            $clean['shortcuts'] = array();
            foreach ( $widget_data['shortcuts'] as $shortcut ) {
                $clean['shortcuts'][] = array(
                    'label' => sanitize_text_field( $shortcut['label'] ?? '' ),
                    'url'   => esc_url_raw( $shortcut['url'] ?? '' ),
                );
            }
        }

        $widgets = get_option( 'wac_custom_widgets', array() );
        $widgets[ $widget_id ] = $clean;
        update_option( 'wac_custom_widgets', $widgets );

        wp_send_json_success( array( 'message' => 'Widget saved', 'widget_id' => $widget_id ) );
    }

    /**
     * AJAX: Delete widget
     */
    public function ajax_delete_widget() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $widget_id = isset( $_POST['widget_id'] ) ? sanitize_key( $_POST['widget_id'] ) : '';
        
        if ( empty( $widget_id ) ) {
            wp_send_json_error( 'Invalid widget ID' );
        }

        $widgets = get_option( 'wac_custom_widgets', array() );
        unset( $widgets[ $widget_id ] );
        update_option( 'wac_custom_widgets', $widgets );

        wp_send_json_success( array( 'message' => 'Widget deleted' ) );
    }

    /**
     * AJAX: Get all widgets
     */
    public function ajax_get_widgets() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        wp_send_json_success( $this->widgets );
    }

    /**
     * Render the widget builder UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        $widgets = $instance->widgets;
        $roles = wp_roles()->get_names();
        ?>
        <style>
        .wac-widget-builder{background:#fff;border:1px solid #e5e5ea;border-radius:8px;overflow:hidden}
        .wac-widget-toolbar{display:flex;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e5ea;background:#f5f5f7}
        .wac-widget-list{padding:0}
        .wac-widget-item{padding:16px;border-bottom:1px solid #e5e5ea;display:flex;align-items:center;justify-content:space-between}
        .wac-widget-item:last-child{border-bottom:none}
        .wac-widget-item:hover{background:#f9f9fb}
        .wac-widget-info{display:flex;align-items:center;gap:12px}
        .wac-widget-info strong{font-size:14px;color:#1d1d1f}
        .wac-widget-info small{font-size:12px;color:#86868b;margin-left:8px}
        .wac-widget-actions{display:flex;gap:8px}
        .wac-widget-status{padding:2px 8px;font-size:11px;border-radius:4px}
        .wac-widget-status.enabled{background:#d4edda;color:#155724}
        .wac-widget-status.disabled{background:#f8d7da;color:#721c24}
        .wac-empty-widgets{text-align:center;padding:40px;color:#86868b}
        
        /* Modal */
        .wac-modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center}
        .wac-modal.open{display:flex}
        .wac-modal-content{background:#fff;border-radius:12px;width:100%;max-width:560px;max-height:90vh;overflow:auto}
        .wac-modal-header{padding:16px 20px;border-bottom:1px solid #e5e5ea;display:flex;justify-content:space-between;align-items:center}
        .wac-modal-header h3{margin:0;font-size:16px}
        .wac-modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:#86868b}
        .wac-modal-body{padding:20px}
        .wac-modal-footer{padding:16px 20px;border-top:1px solid #e5e5ea;display:flex;justify-content:flex-end;gap:8px}
        
        .wac-form-row{margin-bottom:16px}
        .wac-form-row label{display:block;font-size:12px;font-weight:600;color:#1d1d1f;margin-bottom:6px}
        .wac-form-row input,.wac-form-row select,.wac-form-row textarea{width:100%;padding:10px 12px;border:1px solid #d1d1d6;border-radius:6px;font-size:14px}
        .wac-form-row input:focus,.wac-form-row select:focus,.wac-form-row textarea:focus{outline:none;border-color:#007aff}
        .wac-form-row textarea{min-height:100px;resize:vertical}
        </style>
        
        <div class="wac-widget-builder">
            <div class="wac-widget-toolbar">
                <div>
                    <span style="font-size:13px;color:#86868b"><?php echo count( $widgets ); ?> widget(s)</span>
                </div>
                <button type="button" class="wac-btn wac-btn-primary" id="wac-add-widget">Add Widget</button>
            </div>
            
            <div class="wac-widget-list" id="wac-widget-list">
                <?php if ( empty( $widgets ) ) : ?>
                    <div class="wac-empty-widgets">
                        <p>No custom widgets yet. Click "Add Widget" to create one.</p>
                    </div>
                <?php else : ?>
                    <?php foreach ( $widgets as $id => $widget ) : ?>
                    <div class="wac-widget-item" data-id="<?php echo esc_attr( $id ); ?>">
                        <div class="wac-widget-info">
                            <span class="dashicons dashicons-screenoptions" style="color:#86868b"></span>
                            <div>
                                <strong><?php echo esc_html( $widget['title'] ); ?></strong>
                                <small><?php echo esc_html( ucfirst( $widget['type'] ) ); ?></small>
                            </div>
                        </div>
                        <div class="wac-widget-actions">
                            <span class="wac-widget-status <?php echo ! empty( $widget['enabled'] ) ? 'enabled' : 'disabled'; ?>">
                                <?php echo ! empty( $widget['enabled'] ) ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button type="button" class="wac-btn wac-btn-secondary wac-edit-widget">Edit</button>
                            <button type="button" class="wac-btn wac-btn-secondary wac-delete-widget" style="color:#ff3b30">Delete</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Widget Modal -->
        <div class="wac-modal" id="wac-widget-modal">
            <div class="wac-modal-content">
                <div class="wac-modal-header">
                    <h3 id="wac-modal-title">Add Widget</h3>
                    <button type="button" class="wac-modal-close">&times;</button>
                </div>
                <div class="wac-modal-body">
                    <input type="hidden" id="wac-widget-id">
                    
                    <div class="wac-form-row">
                        <label>Widget Title</label>
                        <input type="text" id="wac-widget-title" placeholder="My Widget">
                    </div>
                    
                    <div class="wac-form-row">
                        <label>Widget Type</label>
                        <select id="wac-widget-type">
                            <option value="text">Text / HTML</option>
                            <option value="rss">RSS Feed</option>
                            <option value="stats">Quick Stats</option>
                            <option value="shortcuts">Quick Shortcuts</option>
                            <option value="notes">Personal Notes</option>
                        </select>
                    </div>
                    
                    <div class="wac-form-row" id="wac-content-row">
                        <label>Content</label>
                        <textarea id="wac-widget-content" placeholder="Enter widget content..."></textarea>
                        <small style="color:#86868b">For RSS: enter feed URL. For Text: enter HTML or plain text.</small>
                    </div>
                    
                    <div class="wac-form-row">
                        <label>Visible to Roles (leave empty for all)</label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
                            <?php foreach ( $roles as $role_key => $role_name ) : ?>
                            <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer">
                                <input type="checkbox" class="wac-widget-role" value="<?php echo esc_attr( $role_key ); ?>">
                                <?php echo esc_html( $role_name ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="wac-form-row">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" id="wac-widget-enabled" checked>
                            Enable Widget
                        </label>
                    </div>
                </div>
                <div class="wac-modal-footer">
                    <button type="button" class="wac-btn wac-btn-secondary wac-modal-cancel">Cancel</button>
                    <button type="button" class="wac-btn wac-btn-primary" id="wac-save-widget">Save Widget</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var widgetsData = <?php echo json_encode( $widgets ); ?>;
            
            function generateId() {
                return 'w_' + Date.now();
            }
            
            function openModal(title, widget) {
                $('#wac-modal-title').text(title);
                $('#wac-widget-id').val(widget ? widget.id : generateId());
                $('#wac-widget-title').val(widget ? widget.title : '');
                $('#wac-widget-type').val(widget ? widget.type : 'text');
                $('#wac-widget-content').val(widget ? widget.content : '');
                $('#wac-widget-enabled').prop('checked', widget ? widget.enabled : true);
                
                $('.wac-widget-role').each(function() {
                    var role = $(this).val();
                    $(this).prop('checked', widget && widget.roles && widget.roles.indexOf(role) > -1);
                });
                
                toggleContentField();
                $('#wac-widget-modal').addClass('open');
            }
            
            function closeModal() {
                $('#wac-widget-modal').removeClass('open');
            }
            
            function toggleContentField() {
                var type = $('#wac-widget-type').val();
                var showContent = ['text', 'html', 'rss'].indexOf(type) > -1;
                $('#wac-content-row').toggle(showContent);
            }
            
            // Add widget
            $('#wac-add-widget').on('click', function() {
                openModal('Add Widget', null);
            });
            
            // Edit widget
            $(document).on('click', '.wac-edit-widget', function() {
                var id = $(this).closest('.wac-widget-item').data('id');
                var widget = widgetsData[id];
                if (widget) {
                    widget.id = id;
                    openModal('Edit Widget', widget);
                }
            });
            
            // Delete widget
            $(document).on('click', '.wac-delete-widget', function() {
                if (!confirm('Delete this widget?')) return;
                
                var $item = $(this).closest('.wac-widget-item');
                var id = $item.data('id');
                
                $.post(ajaxurl, {
                    action: 'wac_delete_widget',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    widget_id: id
                }, function(res) {
                    if (res.success) {
                        delete widgetsData[id];
                        $item.fadeOut(function() { $(this).remove(); });
                    }
                });
            });
            
            // Widget type change
            $('#wac-widget-type').on('change', toggleContentField);
            
            // Close modal
            $('.wac-modal-close, .wac-modal-cancel').on('click', closeModal);
            $(document).on('click', '.wac-modal', function(e) {
                if ($(e.target).hasClass('wac-modal')) closeModal();
            });
            
            // Save widget
            $('#wac-save-widget').on('click', function() {
                var $btn = $(this);
                var id = $('#wac-widget-id').val();
                var roles = [];
                $('.wac-widget-role:checked').each(function() {
                    roles.push($(this).val());
                });
                
                var data = {
                    title: $('#wac-widget-title').val() || 'Untitled Widget',
                    type: $('#wac-widget-type').val(),
                    content: $('#wac-widget-content').val(),
                    enabled: $('#wac-widget-enabled').is(':checked'),
                    roles: roles
                };
                
                $btn.text('Saving...');
                
                $.post(ajaxurl, {
                    action: 'wac_save_widget',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    widget_id: id,
                    widget_data: JSON.stringify(data)
                }, function(res) {
                    if (res.success) {
                        widgetsData[id] = data;
                        location.reload();
                    }
                    $btn.text('Save Widget');
                });
            });
        });
        </script>
        <?php
    }
}

// Save user notes AJAX
add_action( 'wp_ajax_wac_save_user_notes', function() {
    check_ajax_referer( 'wac_admin_nonce', 'nonce' );
    $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';
    update_user_meta( get_current_user_id(), 'wac_dashboard_notes', $notes );
    wp_send_json_success();
});

