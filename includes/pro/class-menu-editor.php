<?php
/**
 * Admin Menu Editor - Drag & Drop
 * Reorder, rename, hide, and customize admin menu items
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Menu_Editor {

    private static $instance = null;
    private $menu_data;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->menu_data = get_option( 'wac_menu_editor', array() );
        
        add_action( 'admin_menu', array( $this, 'apply_menu_changes' ), 9999 );
        add_action( 'wp_ajax_wac_save_menu', array( $this, 'ajax_save_menu' ) );
        add_action( 'wp_ajax_wac_reset_menu', array( $this, 'ajax_reset_menu' ) );
        add_action( 'wp_ajax_wac_get_menu', array( $this, 'ajax_get_menu' ) );
    }

    /**
     * Apply saved menu changes
     */
    public function apply_menu_changes() {
        global $menu, $submenu;
        
        if ( empty( $this->menu_data ) || ! is_array( $this->menu_data ) ) {
            return;
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Process each menu item
        foreach ( $this->menu_data as $item_key => $item_data ) {
            // Check role restrictions
            if ( ! empty( $item_data['roles'] ) && is_array( $item_data['roles'] ) ) {
                $has_access = false;
                foreach ( $user_roles as $role ) {
                    if ( in_array( $role, $item_data['roles'] ) ) {
                        $has_access = true;
                        break;
                    }
                }
                if ( ! $has_access && ! current_user_can( 'administrator' ) ) {
                    $this->remove_menu_item( $item_key );
                    continue;
                }
            }
            
            // Hide item
            if ( ! empty( $item_data['hidden'] ) ) {
                $this->remove_menu_item( $item_key );
                continue;
            }
            
            // Find and modify the menu item
            foreach ( $menu as $index => $menu_item ) {
                if ( isset( $menu_item[2] ) && $menu_item[2] === $item_key ) {
                    // Rename
                    if ( ! empty( $item_data['title'] ) ) {
                        $menu[ $index ][0] = $item_data['title'];
                    }
                    // Change icon
                    if ( ! empty( $item_data['icon'] ) ) {
                        $menu[ $index ][6] = $item_data['icon'];
                    }
                    break;
                }
            }
        }
        
        // Reorder menu
        if ( ! empty( $this->menu_data['_order'] ) && is_array( $this->menu_data['_order'] ) ) {
            $new_menu = array();
            $position = 10;
            
            foreach ( $this->menu_data['_order'] as $menu_slug ) {
                foreach ( $menu as $index => $menu_item ) {
                    if ( isset( $menu_item[2] ) && $menu_item[2] === $menu_slug ) {
                        $new_menu[ $position ] = $menu_item;
                        unset( $menu[ $index ] );
                        $position += 10;
                        break;
                    }
                }
            }
            
            // Add remaining items
            foreach ( $menu as $menu_item ) {
                $new_menu[ $position ] = $menu_item;
                $position += 10;
            }
            
            $menu = $new_menu;
        }
    }

    /**
     * Remove a menu item
     */
    private function remove_menu_item( $slug ) {
        global $menu, $submenu;
        
        foreach ( $menu as $index => $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                unset( $menu[ $index ] );
                break;
            }
        }
        
        if ( isset( $submenu[ $slug ] ) ) {
            unset( $submenu[ $slug ] );
        }
    }

    /**
     * Get current menu structure
     */
    public function get_menu_structure() {
        global $menu, $submenu;
        
        $structure = array();
        
        foreach ( $menu as $position => $item ) {
            if ( empty( $item[0] ) || $item[4] === 'wp-menu-separator' ) {
                continue;
            }
            
            $slug = $item[2];
            $title = wp_strip_all_tags( $item[0] );
            $icon = isset( $item[6] ) ? $item[6] : '';
            
            // Get saved data
            $saved = isset( $this->menu_data[ $slug ] ) ? $this->menu_data[ $slug ] : array();
            
            $menu_item = array(
                'slug'        => $slug,
                'title'       => $title,
                'custom_title'=> isset( $saved['title'] ) ? $saved['title'] : '',
                'icon'        => $icon,
                'custom_icon' => isset( $saved['icon'] ) ? $saved['icon'] : '',
                'hidden'      => ! empty( $saved['hidden'] ),
                'roles'       => isset( $saved['roles'] ) ? $saved['roles'] : array(),
                'position'    => $position,
                'submenu'     => array(),
            );
            
            // Get submenu
            if ( isset( $submenu[ $slug ] ) ) {
                foreach ( $submenu[ $slug ] as $sub_position => $sub_item ) {
                    $sub_slug = $sub_item[2];
                    $sub_saved = isset( $this->menu_data[ $slug . '::' . $sub_slug ] ) 
                        ? $this->menu_data[ $slug . '::' . $sub_slug ] : array();
                    
                    $menu_item['submenu'][] = array(
                        'slug'        => $sub_slug,
                        'parent'      => $slug,
                        'title'       => wp_strip_all_tags( $sub_item[0] ),
                        'custom_title'=> isset( $sub_saved['title'] ) ? $sub_saved['title'] : '',
                        'hidden'      => ! empty( $sub_saved['hidden'] ),
                    );
                }
            }
            
            $structure[] = $menu_item;
        }
        
        return $structure;
    }

    /**
     * AJAX: Get menu
     */
    public function ajax_get_menu() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        wp_send_json_success( $this->get_menu_structure() );
    }

    /**
     * AJAX: Save menu
     */
    public function ajax_save_menu() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $data = isset( $_POST['menu_data'] ) ? json_decode( stripslashes( $_POST['menu_data'] ), true ) : array();
        
        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Invalid data' );
        }
        
        // Sanitize
        $clean = array();
        
        if ( isset( $data['_order'] ) && is_array( $data['_order'] ) ) {
            $clean['_order'] = array_map( 'sanitize_text_field', $data['_order'] );
        }
        
        foreach ( $data as $key => $item ) {
            if ( $key === '_order' ) continue;
            
            $clean_key = sanitize_text_field( $key );
            $clean[ $clean_key ] = array(
                'title'  => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
                'icon'   => isset( $item['icon'] ) ? sanitize_text_field( $item['icon'] ) : '',
                'hidden' => ! empty( $item['hidden'] ),
                'roles'  => isset( $item['roles'] ) && is_array( $item['roles'] ) 
                    ? array_map( 'sanitize_key', $item['roles'] ) : array(),
            );
        }
        
        update_option( 'wac_menu_editor', $clean );
        $this->menu_data = $clean;
        
        wp_send_json_success( array( 'message' => 'Menu saved' ) );
    }

    /**
     * AJAX: Reset menu
     */
    public function ajax_reset_menu() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        delete_option( 'wac_menu_editor' );
        $this->menu_data = array();
        
        wp_send_json_success( array( 'message' => 'Menu reset' ) );
    }

    /**
     * Render the menu editor UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        $menu = $instance->get_menu_structure();
        $roles = wp_roles()->get_names();
        
        $icons = array(
            'dashicons-admin-home', 'dashicons-admin-post', 'dashicons-admin-media',
            'dashicons-admin-links', 'dashicons-admin-page', 'dashicons-admin-comments',
            'dashicons-admin-appearance', 'dashicons-admin-plugins', 'dashicons-admin-users',
            'dashicons-admin-tools', 'dashicons-admin-settings', 'dashicons-admin-network',
            'dashicons-admin-generic', 'dashicons-dashboard', 'dashicons-cart',
            'dashicons-store', 'dashicons-products', 'dashicons-archive',
            'dashicons-tag', 'dashicons-category', 'dashicons-format-gallery',
            'dashicons-calendar', 'dashicons-calendar-alt', 'dashicons-email',
            'dashicons-email-alt', 'dashicons-businessman', 'dashicons-groups',
            'dashicons-clipboard', 'dashicons-chart-bar', 'dashicons-chart-pie',
            'dashicons-chart-line', 'dashicons-chart-area', 'dashicons-analytics',
            'dashicons-hammer', 'dashicons-art', 'dashicons-migrate',
            'dashicons-performance', 'dashicons-universal-access', 'dashicons-tickets',
            'dashicons-nametag', 'dashicons-portfolio', 'dashicons-book',
            'dashicons-book-alt', 'dashicons-download', 'dashicons-upload',
            'dashicons-clock', 'dashicons-lightbulb', 'dashicons-microphone',
            'dashicons-desktop', 'dashicons-laptop', 'dashicons-tablet',
            'dashicons-smartphone', 'dashicons-phone', 'dashicons-index-card',
            'dashicons-building', 'dashicons-store', 'dashicons-album',
            'dashicons-palmtree', 'dashicons-tickets-alt', 'dashicons-money',
            'dashicons-money-alt', 'dashicons-smiley', 'dashicons-thumbs-up',
            'dashicons-thumbs-down', 'dashicons-layout', 'dashicons-paperclip',
        );
        ?>
        <style>
        .wac-menu-editor{background:#fff;border:1px solid #e5e5ea;border-radius:8px;overflow:hidden}
        .wac-menu-toolbar{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e5ea;background:#f5f5f7}
        .wac-menu-toolbar-left{display:flex;gap:8px}
        .wac-menu-list{list-style:none;margin:0;padding:0}
        .wac-menu-item{border-bottom:1px solid #e5e5ea;background:#fff}
        .wac-menu-item:last-child{border-bottom:none}
        .wac-menu-item.dragging{opacity:.5;background:#f0f5ff}
        .wac-menu-item.hidden-item .wac-menu-header{opacity:.5}
        .wac-menu-header{display:flex;align-items:center;padding:12px 16px;cursor:move;gap:12px}
        .wac-menu-header:hover{background:#f9f9fb}
        .wac-menu-drag{color:#86868b;cursor:grab}
        .wac-menu-drag:active{cursor:grabbing}
        .wac-menu-icon{width:24px;height:24px;display:flex;align-items:center;justify-content:center;color:#1d1d1f}
        .wac-menu-icon .dashicons{font-size:20px;width:20px;height:20px}
        .wac-menu-title{flex:1;font-size:14px;font-weight:500;color:#1d1d1f}
        .wac-menu-title small{font-weight:400;color:#86868b;margin-left:6px}
        .wac-menu-actions{display:flex;gap:4px}
        .wac-menu-actions button{background:none;border:none;padding:6px 8px;cursor:pointer;color:#86868b;border-radius:4px}
        .wac-menu-actions button:hover{background:#e5e5ea;color:#1d1d1f}
        .wac-menu-actions button.active{color:#007aff}
        .wac-submenu-list{list-style:none;margin:0;padding:0 0 0 52px;background:#f9f9fb;border-top:1px solid #e5e5ea}
        .wac-submenu-item{display:flex;align-items:center;padding:10px 16px;border-bottom:1px solid #e5e5ea;gap:12px}
        .wac-submenu-item:last-child{border-bottom:none}
        .wac-submenu-item.hidden-item{opacity:.5}
        .wac-submenu-title{flex:1;font-size:13px;color:#1d1d1f}
        
        /* Edit Panel */
        .wac-edit-panel{display:none;padding:16px;background:#f9f9fb;border-top:1px solid #e5e5ea}
        .wac-edit-panel.open{display:block}
        .wac-edit-row{display:flex;gap:16px;margin-bottom:12px}
        .wac-edit-row:last-child{margin-bottom:0}
        .wac-edit-field{flex:1}
        .wac-edit-field label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#86868b;margin-bottom:4px}
        .wac-edit-field input{width:100%;padding:8px 12px;border:1px solid #d1d1d6;border-radius:6px;font-size:13px}
        .wac-edit-field input:focus{outline:none;border-color:#007aff}
        
        /* Icon Picker */
        .wac-icon-picker{position:relative}
        .wac-icon-picker-btn{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #d1d1d6;border-radius:6px;background:#fff;cursor:pointer;width:100%}
        .wac-icon-picker-btn .dashicons{font-size:16px;width:16px;height:16px}
        .wac-icon-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d1d6;border-radius:6px;padding:8px;display:none;z-index:100;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1)}
        .wac-icon-dropdown.open{display:grid;grid-template-columns:repeat(8,1fr);gap:4px}
        .wac-icon-option{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:4px;cursor:pointer}
        .wac-icon-option:hover{background:#e5e5ea}
        .wac-icon-option.selected{background:#007aff;color:#fff}
        
        /* Role Checkboxes */
        .wac-role-list{display:flex;flex-wrap:wrap;gap:8px}
        .wac-role-check{display:flex;align-items:center;gap:4px;font-size:12px;padding:4px 8px;background:#e5e5ea;border-radius:4px;cursor:pointer}
        .wac-role-check:hover{background:#d1d1d6}
        .wac-role-check input{margin:0}
        .wac-role-check.checked{background:#007aff;color:#fff}
        
        /* Buttons */
        .wac-btn{padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none}
        .wac-btn-primary{background:#007aff;color:#fff}
        .wac-btn-primary:hover{background:#0056b3}
        .wac-btn-secondary{background:#e5e5ea;color:#1d1d1f}
        .wac-btn-secondary:hover{background:#d1d1d6}
        .wac-btn-danger{background:#ff3b30;color:#fff}
        .wac-btn-danger:hover{background:#d63029}
        
        .wac-save-status{font-size:12px;color:#86868b;padding:8px 0}
        .wac-save-status.success{color:#34c759}
        .wac-save-status.error{color:#ff3b30}
        </style>
        
        <div class="wac-menu-editor">
            <div class="wac-menu-toolbar">
                <div class="wac-menu-toolbar-left">
                    <button type="button" class="wac-btn wac-btn-secondary" id="wac-expand-all">Expand All</button>
                    <button type="button" class="wac-btn wac-btn-secondary" id="wac-collapse-all">Collapse All</button>
                </div>
                <div class="wac-menu-toolbar-right">
                    <span class="wac-save-status" id="wac-save-status"></span>
                    <button type="button" class="wac-btn wac-btn-danger" id="wac-reset-menu">Reset</button>
                    <button type="button" class="wac-btn wac-btn-primary" id="wac-save-menu">Save Menu</button>
                </div>
            </div>
            
            <ul class="wac-menu-list" id="wac-menu-list">
                <?php foreach ( $menu as $item ) : ?>
                <li class="wac-menu-item <?php echo $item['hidden'] ? 'hidden-item' : ''; ?>" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
                    <div class="wac-menu-header">
                        <span class="wac-menu-drag dashicons dashicons-menu"></span>
                        <span class="wac-menu-icon">
                            <span class="dashicons <?php echo esc_attr( $item['custom_icon'] ?: $item['icon'] ); ?>"></span>
                        </span>
                        <span class="wac-menu-title">
                            <?php echo esc_html( $item['custom_title'] ?: $item['title'] ); ?>
                            <?php if ( $item['custom_title'] && $item['custom_title'] !== $item['title'] ) : ?>
                                <small>(<?php echo esc_html( $item['title'] ); ?>)</small>
                            <?php endif; ?>
                        </span>
                        <div class="wac-menu-actions">
                            <button type="button" class="wac-toggle-visibility <?php echo $item['hidden'] ? '' : 'active'; ?>" title="Toggle Visibility">
                                <span class="dashicons dashicons-<?php echo $item['hidden'] ? 'hidden' : 'visibility'; ?>"></span>
                            </button>
                            <button type="button" class="wac-edit-item" title="Edit">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <?php if ( ! empty( $item['submenu'] ) ) : ?>
                            <button type="button" class="wac-toggle-submenu" title="Toggle Submenu">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="wac-edit-panel" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
                        <div class="wac-edit-row">
                            <div class="wac-edit-field">
                                <label>Custom Title</label>
                                <input type="text" class="wac-custom-title" value="<?php echo esc_attr( $item['custom_title'] ); ?>" placeholder="<?php echo esc_attr( $item['title'] ); ?>">
                            </div>
                            <div class="wac-edit-field">
                                <label>Icon</label>
                                <div class="wac-icon-picker">
                                    <button type="button" class="wac-icon-picker-btn">
                                        <span class="dashicons <?php echo esc_attr( $item['custom_icon'] ?: $item['icon'] ); ?>"></span>
                                        <span>Change Icon</span>
                                    </button>
                                    <div class="wac-icon-dropdown">
                                        <?php foreach ( $icons as $icon ) : ?>
                                        <span class="wac-icon-option <?php echo ( $item['custom_icon'] === $icon || ( ! $item['custom_icon'] && $item['icon'] === $icon ) ) ? 'selected' : ''; ?>" data-icon="<?php echo esc_attr( $icon ); ?>">
                                            <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wac-edit-row">
                            <div class="wac-edit-field">
                                <label>Visible to Roles (leave empty for all)</label>
                                <div class="wac-role-list">
                                    <?php foreach ( $roles as $role_key => $role_name ) : ?>
                                    <label class="wac-role-check <?php echo in_array( $role_key, $item['roles'] ) ? 'checked' : ''; ?>">
                                        <input type="checkbox" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $item['roles'] ) ); ?>>
                                        <?php echo esc_html( $role_name ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ( ! empty( $item['submenu'] ) ) : ?>
                    <ul class="wac-submenu-list" style="display:none">
                        <?php foreach ( $item['submenu'] as $sub ) : ?>
                        <li class="wac-submenu-item <?php echo $sub['hidden'] ? 'hidden-item' : ''; ?>" data-slug="<?php echo esc_attr( $item['slug'] . '::' . $sub['slug'] ); ?>">
                            <span class="wac-submenu-title">
                                <?php echo esc_html( $sub['custom_title'] ?: $sub['title'] ); ?>
                            </span>
                            <input type="text" class="wac-custom-title" value="<?php echo esc_attr( $sub['custom_title'] ); ?>" placeholder="<?php echo esc_attr( $sub['title'] ); ?>" style="width:150px;padding:4px 8px;font-size:12px">
                            <button type="button" class="wac-toggle-visibility <?php echo $sub['hidden'] ? '' : 'active'; ?>">
                                <span class="dashicons dashicons-<?php echo $sub['hidden'] ? 'hidden' : 'visibility'; ?>"></span>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <script>
        jQuery(function($) {
            var menuData = {};
            var isDirty = false;
            
            // Initialize Sortable
            if (typeof Sortable !== 'undefined') {
                new Sortable(document.getElementById('wac-menu-list'), {
                    animation: 150,
                    handle: '.wac-menu-drag',
                    ghostClass: 'dragging',
                    onEnd: function() {
                        isDirty = true;
                        updateOrder();
                    }
                });
            }
            
            function updateOrder() {
                menuData._order = [];
                $('#wac-menu-list .wac-menu-item').each(function() {
                    menuData._order.push($(this).data('slug'));
                });
            }
            
            // Toggle visibility
            $(document).on('click', '.wac-toggle-visibility', function(e) {
                e.stopPropagation();
                var $btn = $(this);
                var $item = $btn.closest('.wac-menu-item, .wac-submenu-item');
                var slug = $item.data('slug');
                var isHidden = $btn.hasClass('active');
                
                $btn.toggleClass('active');
                $item.toggleClass('hidden-item');
                $btn.find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
                
                if (!menuData[slug]) menuData[slug] = {};
                menuData[slug].hidden = isHidden;
                isDirty = true;
            });
            
            // Edit item
            $(document).on('click', '.wac-edit-item', function(e) {
                e.stopPropagation();
                $(this).closest('.wac-menu-item').find('.wac-edit-panel').toggleClass('open');
            });
            
            // Toggle submenu
            $(document).on('click', '.wac-toggle-submenu', function(e) {
                e.stopPropagation();
                var $submenu = $(this).closest('.wac-menu-item').find('.wac-submenu-list');
                $submenu.slideToggle(200);
                $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            });
            
            // Custom title change
            $(document).on('input', '.wac-custom-title', function() {
                var $item = $(this).closest('.wac-menu-item, .wac-submenu-item');
                var slug = $item.data('slug');
                
                if (!menuData[slug]) menuData[slug] = {};
                menuData[slug].title = $(this).val();
                isDirty = true;
            });
            
            // Icon picker
            $(document).on('click', '.wac-icon-picker-btn', function(e) {
                e.stopPropagation();
                $(this).siblings('.wac-icon-dropdown').toggleClass('open');
            });
            
            $(document).on('click', '.wac-icon-option', function() {
                var icon = $(this).data('icon');
                var $picker = $(this).closest('.wac-icon-picker');
                var $item = $(this).closest('.wac-menu-item');
                var slug = $item.data('slug');
                
                $picker.find('.wac-icon-option').removeClass('selected');
                $(this).addClass('selected');
                $picker.find('.wac-icon-picker-btn .dashicons').attr('class', 'dashicons ' + icon);
                $picker.find('.wac-icon-dropdown').removeClass('open');
                $item.find('.wac-menu-icon .dashicons').attr('class', 'dashicons ' + icon);
                
                if (!menuData[slug]) menuData[slug] = {};
                menuData[slug].icon = icon;
                isDirty = true;
            });
            
            // Role checkbox
            $(document).on('change', '.wac-role-check input', function() {
                var $label = $(this).closest('.wac-role-check');
                var $item = $(this).closest('.wac-menu-item');
                var slug = $item.data('slug');
                
                $label.toggleClass('checked', $(this).is(':checked'));
                
                if (!menuData[slug]) menuData[slug] = {};
                menuData[slug].roles = [];
                $item.find('.wac-role-check input:checked').each(function() {
                    menuData[slug].roles.push($(this).val());
                });
                isDirty = true;
            });
            
            // Close dropdowns on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wac-icon-picker').length) {
                    $('.wac-icon-dropdown').removeClass('open');
                }
            });
            
            // Expand/Collapse all
            $('#wac-expand-all').on('click', function() {
                $('.wac-submenu-list').slideDown(200);
                $('.wac-toggle-submenu .dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            });
            
            $('#wac-collapse-all').on('click', function() {
                $('.wac-submenu-list').slideUp(200);
                $('.wac-toggle-submenu .dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            });
            
            // Save
            $('#wac-save-menu').on('click', function() {
                var $btn = $(this);
                var $status = $('#wac-save-status');
                
                updateOrder();
                
                $btn.prop('disabled', true).text('Saving...');
                $status.text('').removeClass('success error');
                
                $.post(ajaxurl, {
                    action: 'wac_save_menu',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    menu_data: JSON.stringify(menuData)
                }, function(response) {
                    if (response.success) {
                        $status.text('Saved').addClass('success');
                        isDirty = false;
                    } else {
                        $status.text('Error saving').addClass('error');
                    }
                    $btn.prop('disabled', false).text('Save Menu');
                    
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                });
            });
            
            // Reset
            $('#wac-reset-menu').on('click', function() {
                if (!confirm('Reset all menu customizations? This cannot be undone.')) return;
                
                $.post(ajaxurl, {
                    action: 'wac_reset_menu',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Warn on leave
            $(window).on('beforeunload', function() {
                if (isDirty) {
                    return 'You have unsaved changes.';
                }
            });
        });
        </script>
        <?php
    }
}

