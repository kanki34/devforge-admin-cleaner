<?php
/**
 * Command Palette
 * Spotlight-like quick search for WordPress admin
 * Press Cmd/Ctrl + K to open
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Command_Palette {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Always add hooks, check enabled status in each method
        add_action( 'admin_footer', array( $this, 'render_palette' ) );
        add_action( 'wp_ajax_wac_command_search', array( $this, 'ajax_search' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 5 );
    }
    
    /**
     * Check if command palette is enabled
     */
    private function is_enabled() {
        $options = get_option( 'wac_settings', array() );
        // If not set, default to enabled (backward compatibility)
        if ( ! isset( $options['command_palette_enabled'] ) ) {
            return true;
        }
        // Check if explicitly enabled - must be 1, '1', or true (not 0, '0', false, or empty)
        $enabled = $options['command_palette_enabled'];
        return $enabled == '1' || $enabled === true || $enabled === 1;
    }

    /**
     * AJAX Search
     */
    public function ajax_search() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $query = sanitize_text_field( $_POST['query'] ?? '' );
        $results = array();

        if ( strlen( $query ) < 2 ) {
            wp_send_json_success( $results );
        }

        // Search posts
        $posts = get_posts( array(
            's' => $query,
            'post_type' => 'any',
            'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 5,
        ) );

        foreach ( $posts as $post ) {
            $type_obj = get_post_type_object( $post->post_type );
            $results[] = array(
                'type' => 'post',
                'icon' => 'dashicons-admin-post',
                'title' => $post->post_title,
                'subtitle' => $type_obj->labels->singular_name . ' · ' . ucfirst( $post->post_status ),
                'url' => get_edit_post_link( $post->ID, 'raw' ),
            );
        }

        // Search pages
        if ( count( $results ) < 8 ) {
            $pages = get_posts( array(
                's' => $query,
                'post_type' => 'page',
                'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 3,
            ) );

            foreach ( $pages as $page ) {
                $exists = false;
                foreach ( $results as $r ) {
                    if ( strpos( $r['url'], 'post=' . $page->ID ) !== false ) {
                        $exists = true;
                        break;
                    }
                }
                if ( ! $exists ) {
                    $results[] = array(
                        'type' => 'page',
                        'icon' => 'dashicons-admin-page',
                        'title' => $page->post_title,
                        'subtitle' => 'Page · ' . ucfirst( $page->post_status ),
                        'url' => get_edit_post_link( $page->ID, 'raw' ),
                    );
                }
            }
        }

        // Search users
        if ( current_user_can( 'list_users' ) && count( $results ) < 8 ) {
            $users = get_users( array(
                'search' => '*' . $query . '*',
                'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
                'number' => 3,
            ) );

            foreach ( $users as $user ) {
                $results[] = array(
                    'type' => 'user',
                    'icon' => 'dashicons-admin-users',
                    'title' => $user->display_name,
                    'subtitle' => 'User · ' . $user->user_email,
                    'url' => get_edit_user_link( $user->ID ),
                );
            }
        }

        // Search admin menus
        $menus = $this->get_admin_menus();
        foreach ( $menus as $menu ) {
            if ( stripos( $menu['title'], $query ) !== false && count( $results ) < 10 ) {
                $results[] = array(
                    'type' => 'menu',
                    'icon' => $menu['icon'],
                    'title' => $menu['title'],
                    'subtitle' => 'Admin Page',
                    'url' => $menu['url'],
                );
            }
        }

        // Search plugins
        if ( current_user_can( 'activate_plugins' ) && count( $results ) < 10 ) {
            $plugins = get_plugins();
            foreach ( $plugins as $path => $plugin ) {
                if ( stripos( $plugin['Name'], $query ) !== false ) {
                    $results[] = array(
                        'type' => 'plugin',
                        'icon' => 'dashicons-admin-plugins',
                        'title' => $plugin['Name'],
                        'subtitle' => 'Plugin · v' . $plugin['Version'],
                        'url' => admin_url( 'plugins.php?s=' . urlencode( $plugin['Name'] ) ),
                    );
                    if ( count( $results ) >= 10 ) break;
                }
            }
        }

        // Quick actions
        $actions = $this->get_quick_actions();
        foreach ( $actions as $action ) {
            if ( stripos( $action['title'], $query ) !== false && count( $results ) < 12 ) {
                $results[] = $action;
            }
        }

        wp_send_json_success( $results );
    }

    /**
     * Get admin menu items for search
     */
    private function get_admin_menus() {
        return array(
            array( 'title' => 'Dashboard', 'url' => admin_url(), 'icon' => 'dashicons-dashboard' ),
            array( 'title' => 'All Posts', 'url' => admin_url( 'edit.php' ), 'icon' => 'dashicons-admin-post' ),
            array( 'title' => 'Add New Post', 'url' => admin_url( 'post-new.php' ), 'icon' => 'dashicons-plus' ),
            array( 'title' => 'All Pages', 'url' => admin_url( 'edit.php?post_type=page' ), 'icon' => 'dashicons-admin-page' ),
            array( 'title' => 'Add New Page', 'url' => admin_url( 'post-new.php?post_type=page' ), 'icon' => 'dashicons-plus' ),
            array( 'title' => 'Media Library', 'url' => admin_url( 'upload.php' ), 'icon' => 'dashicons-admin-media' ),
            array( 'title' => 'Upload Media', 'url' => admin_url( 'media-new.php' ), 'icon' => 'dashicons-upload' ),
            array( 'title' => 'Comments', 'url' => admin_url( 'edit-comments.php' ), 'icon' => 'dashicons-admin-comments' ),
            array( 'title' => 'Themes', 'url' => admin_url( 'themes.php' ), 'icon' => 'dashicons-admin-appearance' ),
            array( 'title' => 'Customize', 'url' => admin_url( 'customize.php' ), 'icon' => 'dashicons-admin-customizer' ),
            array( 'title' => 'Widgets', 'url' => admin_url( 'widgets.php' ), 'icon' => 'dashicons-screenoptions' ),
            array( 'title' => 'Menus', 'url' => admin_url( 'nav-menus.php' ), 'icon' => 'dashicons-menu' ),
            array( 'title' => 'Plugins', 'url' => admin_url( 'plugins.php' ), 'icon' => 'dashicons-admin-plugins' ),
            array( 'title' => 'Add New Plugin', 'url' => admin_url( 'plugin-install.php' ), 'icon' => 'dashicons-plus' ),
            array( 'title' => 'Users', 'url' => admin_url( 'users.php' ), 'icon' => 'dashicons-admin-users' ),
            array( 'title' => 'Add New User', 'url' => admin_url( 'user-new.php' ), 'icon' => 'dashicons-plus' ),
            array( 'title' => 'Profile', 'url' => admin_url( 'profile.php' ), 'icon' => 'dashicons-id' ),
            array( 'title' => 'Tools', 'url' => admin_url( 'tools.php' ), 'icon' => 'dashicons-admin-tools' ),
            array( 'title' => 'Import', 'url' => admin_url( 'import.php' ), 'icon' => 'dashicons-download' ),
            array( 'title' => 'Export', 'url' => admin_url( 'export.php' ), 'icon' => 'dashicons-upload' ),
            array( 'title' => 'General Settings', 'url' => admin_url( 'options-general.php' ), 'icon' => 'dashicons-admin-settings' ),
            array( 'title' => 'Writing Settings', 'url' => admin_url( 'options-writing.php' ), 'icon' => 'dashicons-edit' ),
            array( 'title' => 'Reading Settings', 'url' => admin_url( 'options-reading.php' ), 'icon' => 'dashicons-book' ),
            array( 'title' => 'Discussion Settings', 'url' => admin_url( 'options-discussion.php' ), 'icon' => 'dashicons-format-chat' ),
            array( 'title' => 'Permalink Settings', 'url' => admin_url( 'options-permalink.php' ), 'icon' => 'dashicons-admin-links' ),
            array( 'title' => 'Privacy Settings', 'url' => admin_url( 'options-privacy.php' ), 'icon' => 'dashicons-privacy' ),
            array( 'title' => 'Site Health', 'url' => admin_url( 'site-health.php' ), 'icon' => 'dashicons-heart' ),
            array( 'title' => 'Admin Cleaner', 'url' => admin_url( 'admin.php?page=webtapot-admin-cleaner' ), 'icon' => 'dashicons-admin-tools' ),
        );
    }

    /**
     * Get quick actions
     */
    private function get_quick_actions() {
        return array(
            array(
                'type' => 'action',
                'icon' => 'dashicons-update',
                'title' => 'Check for Updates',
                'subtitle' => 'Quick Action',
                'url' => admin_url( 'update-core.php' ),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-trash',
                'title' => 'Empty Trash',
                'subtitle' => 'Quick Action',
                'url' => admin_url( 'edit.php?post_status=trash&post_type=post' ),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-shield',
                'title' => 'Security Settings',
                'subtitle' => 'Admin Cleaner',
                'url' => admin_url( 'admin.php?page=webtapot-admin-cleaner&tab=security' ),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-database',
                'title' => 'Database Cleanup',
                'subtitle' => 'Admin Cleaner',
                'url' => admin_url( 'admin.php?page=webtapot-admin-cleaner&tab=tools' ),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-hammer',
                'title' => 'Maintenance Mode',
                'subtitle' => 'Admin Cleaner',
                'url' => admin_url( 'admin.php?page=webtapot-admin-cleaner&tab=features' ),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-external',
                'title' => 'View Site',
                'subtitle' => 'Quick Action',
                'url' => home_url(),
            ),
            array(
                'type' => 'action',
                'icon' => 'dashicons-exit',
                'title' => 'Logout',
                'subtitle' => 'Quick Action',
                'url' => wp_logout_url(),
            ),
        );
    }

    /**
     * Add admin bar button
     */
    public function add_admin_bar_button( $wp_admin_bar ) {
        if ( ! is_admin() || ! $this->is_enabled() ) {
            return;
        }
        
        $options = get_option( 'wac_settings', array() );
        $show_icon = ! isset( $options['command_palette_show_admin_bar_icon'] ) || $options['command_palette_show_admin_bar_icon'] == '1';
        
        if ( ! $show_icon ) {
            return;
        }
        
        $wp_admin_bar->add_node( array(
            'id'    => 'wac-command-palette',
            'title' => '<span class="ab-icon dashicons dashicons-search"></span><span class="ab-label" style="font-size:11px">⌘K</span>',
            'href'  => '#',
            'meta'  => array(
                'title' => 'Quick Search (Cmd/Ctrl + K)',
            ),
        ) );
    }

    /**
     * Render the command palette HTML
     */
    public function render_palette() {
        if ( ! $this->is_enabled() ) {
            return;
        }
        ?>
        <div id="wac-command-palette" class="wac-palette">
            <div class="wac-palette-backdrop"></div>
            <div class="wac-palette-modal">
                <div class="wac-palette-input-wrap">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="wac-palette-input" placeholder="Search posts, pages, settings, users..." autocomplete="off">
                    <kbd>ESC</kbd>
                </div>
                <div class="wac-palette-results" id="wac-palette-results">
                    <div class="wac-palette-hint">
                        <p>Start typing to search...</p>
                        <div class="wac-palette-shortcuts">
                            <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                            <span><kbd>↵</kbd> Open</span>
                            <span><kbd>ESC</kbd> Close</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .wac-palette{display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999}
        .wac-palette.open{display:block}
        .wac-palette-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px)}
        .wac-palette-modal{position:absolute;top:15%;left:50%;transform:translateX(-50%);width:100%;max-width:620px;background:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;animation:wac-slide-down .15s ease-out}
        @keyframes wac-slide-down{from{opacity:0;transform:translateX(-50%) translateY(-10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
        .wac-palette-input-wrap{display:flex;align-items:center;padding:16px 20px;border-bottom:1px solid #e5e5ea;gap:12px}
        .wac-palette-input-wrap .dashicons{color:#86868b;font-size:20px;width:20px;height:20px}
        .wac-palette-input-wrap input{flex:1;border:none;outline:none;font-size:16px;font-family:inherit;background:transparent}
        .wac-palette-input-wrap input::placeholder{color:#86868b}
        .wac-palette-input-wrap kbd{background:#e5e5ea;border-radius:4px;padding:4px 8px;font-size:11px;font-family:inherit;color:#86868b}
        .wac-palette-results{max-height:400px;overflow-y:auto}
        .wac-palette-hint{padding:40px 20px;text-align:center;color:#86868b}
        .wac-palette-hint p{margin:0 0 16px;font-size:14px}
        .wac-palette-shortcuts{display:flex;justify-content:center;gap:20px;font-size:12px}
        .wac-palette-shortcuts kbd{margin-right:4px}
        .wac-palette-item{display:flex;align-items:center;padding:12px 20px;gap:12px;cursor:pointer;border-bottom:1px solid #f5f5f7}
        .wac-palette-item:hover,.wac-palette-item.selected{background:#f5f5f7}
        .wac-palette-item:last-child{border-bottom:none}
        .wac-palette-icon{width:36px;height:36px;border-radius:8px;background:#e5e5ea;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .wac-palette-icon .dashicons{font-size:18px;width:18px;height:18px;color:#1d1d1f}
        .wac-palette-item[data-type="post"] .wac-palette-icon{background:#e3f2fd}
        .wac-palette-item[data-type="post"] .wac-palette-icon .dashicons{color:#1976d2}
        .wac-palette-item[data-type="page"] .wac-palette-icon{background:#f3e5f5}
        .wac-palette-item[data-type="page"] .wac-palette-icon .dashicons{color:#7b1fa2}
        .wac-palette-item[data-type="user"] .wac-palette-icon{background:#e8f5e9}
        .wac-palette-item[data-type="user"] .wac-palette-icon .dashicons{color:#388e3c}
        .wac-palette-item[data-type="action"] .wac-palette-icon{background:#fff3e0}
        .wac-palette-item[data-type="action"] .wac-palette-icon .dashicons{color:#f57c00}
        .wac-palette-item[data-type="plugin"] .wac-palette-icon{background:#fce4ec}
        .wac-palette-item[data-type="plugin"] .wac-palette-icon .dashicons{color:#c2185b}
        .wac-palette-text{flex:1;min-width:0}
        .wac-palette-title{font-size:14px;font-weight:500;color:#1d1d1f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .wac-palette-subtitle{font-size:12px;color:#86868b;margin-top:2px}
        .wac-palette-empty{padding:40px 20px;text-align:center;color:#86868b}
        .wac-palette-loading{padding:40px 20px;text-align:center;color:#86868b}
        
        /* Admin bar button */
        #wpadminbar #wp-admin-bar-wac-command-palette .ab-icon:before{content:"\f179";top:2px}
        </style>

        <script>
        jQuery(function($) {
            var $palette = $('#wac-command-palette');
            var $input = $('#wac-palette-input');
            var $results = $('#wac-palette-results');
            var selectedIndex = -1;
            var searchTimeout;
            var isOpen = false;
            
            // Check if enabled - use PHP value directly
            var isEnabled = <?php echo $this->is_enabled() ? 'true' : 'false'; ?>;
            
            if (!isEnabled) {
                // Remove event listeners if disabled
                return;
            }

            // Open with Cmd/Ctrl + K - check enabled on each keypress
            $(document).on('keydown', function(e) {
                // Re-check enabled status dynamically
                var currentlyEnabled = <?php echo $this->is_enabled() ? 'true' : 'false'; ?>;
                if (!currentlyEnabled && !isOpen) return;
                
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    if (currentlyEnabled) {
                        openPalette();
                    }
                }
                if (e.key === 'Escape' && isOpen) {
                    closePalette();
                }
            });

            // Open from admin bar
            $(document).on('click', '#wp-admin-bar-wac-command-palette a', function(e) {
                e.preventDefault();
                openPalette();
            });

            function openPalette() {
                $palette.addClass('open');
                $input.val('').focus();
                $results.html('<div class="wac-palette-hint"><p>Start typing to search...</p><div class="wac-palette-shortcuts"><span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span><span><kbd>↵</kbd> Open</span><span><kbd>ESC</kbd> Close</span></div></div>');
                selectedIndex = -1;
                isOpen = true;
            }

            function closePalette() {
                $palette.removeClass('open');
                isOpen = false;
            }

            // Close on backdrop click
            $('.wac-palette-backdrop').on('click', closePalette);

            // Search
            $input.on('input', function() {
                var query = $(this).val();
                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $results.html('<div class="wac-palette-hint"><p>Start typing to search...</p><div class="wac-palette-shortcuts"><span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span><span><kbd>↵</kbd> Open</span><span><kbd>ESC</kbd> Close</span></div></div>');
                    return;
                }

                $results.html('<div class="wac-palette-loading">Searching...</div>');

                searchTimeout = setTimeout(function() {
                    $.post(ajaxurl, {
                        action: 'wac_command_search',
                        nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                        query: query
                    }, function(response) {
                        if (response.success && response.data.length) {
                            var html = '';
                            response.data.forEach(function(item, i) {
                                html += '<div class="wac-palette-item' + (i === 0 ? ' selected' : '') + '" data-type="' + item.type + '" data-url="' + item.url + '">';
                                html += '<div class="wac-palette-icon"><span class="dashicons ' + item.icon + '"></span></div>';
                                html += '<div class="wac-palette-text">';
                                html += '<div class="wac-palette-title">' + escHtml(item.title) + '</div>';
                                html += '<div class="wac-palette-subtitle">' + escHtml(item.subtitle) + '</div>';
                                html += '</div></div>';
                            });
                            $results.html(html);
                            selectedIndex = 0;
                        } else {
                            $results.html('<div class="wac-palette-empty">No results found</div>');
                            selectedIndex = -1;
                        }
                    });
                }, 150);
            });

            // Keyboard navigation
            $input.on('keydown', function(e) {
                var $items = $results.find('.wac-palette-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (selectedIndex < $items.length - 1) {
                        selectedIndex++;
                        updateSelection($items);
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (selectedIndex > 0) {
                        selectedIndex--;
                        updateSelection($items);
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    var $selected = $items.eq(selectedIndex);
                    if ($selected.length) {
                        window.location.href = $selected.data('url');
                    }
                }
            });

            function updateSelection($items) {
                $items.removeClass('selected');
                $items.eq(selectedIndex).addClass('selected');
                // Scroll into view
                var $selected = $items.eq(selectedIndex);
                if ($selected.length) {
                    $selected[0].scrollIntoView({ block: 'nearest' });
                }
            }

            // Click on item
            $(document).on('click', '.wac-palette-item', function() {
                window.location.href = $(this).data('url');
            });

            // Hover
            $(document).on('mouseenter', '.wac-palette-item', function() {
                var $items = $results.find('.wac-palette-item');
                selectedIndex = $items.index(this);
                updateSelection($items);
            });

            function escHtml(str) {
                if (!str) return '';
                return $('<div>').text(str).html();
            }
        });
        </script>
        <?php
    }
}

