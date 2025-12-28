<?php
/**
 * Role Editor
 * Create, edit, clone, and delete user roles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Role_Editor {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wac_create_role', array( $this, 'ajax_create_role' ) );
        add_action( 'wp_ajax_wac_update_role', array( $this, 'ajax_update_role' ) );
        add_action( 'wp_ajax_wac_delete_role', array( $this, 'ajax_delete_role' ) );
        add_action( 'wp_ajax_wac_clone_role', array( $this, 'ajax_clone_role' ) );
        add_action( 'wp_ajax_wac_get_role_caps', array( $this, 'ajax_get_role_caps' ) );
    }

    /**
     * Get all capabilities grouped by category
     */
    public static function get_all_capabilities() {
        $caps = array(
            'posts' => array(
                'label' => 'Posts',
                'caps' => array(
                    'edit_posts' => 'Edit Posts',
                    'edit_others_posts' => 'Edit Others Posts',
                    'edit_published_posts' => 'Edit Published Posts',
                    'publish_posts' => 'Publish Posts',
                    'delete_posts' => 'Delete Posts',
                    'delete_others_posts' => 'Delete Others Posts',
                    'delete_published_posts' => 'Delete Published Posts',
                    'delete_private_posts' => 'Delete Private Posts',
                    'edit_private_posts' => 'Edit Private Posts',
                    'read_private_posts' => 'Read Private Posts',
                )
            ),
            'pages' => array(
                'label' => 'Pages',
                'caps' => array(
                    'edit_pages' => 'Edit Pages',
                    'edit_others_pages' => 'Edit Others Pages',
                    'edit_published_pages' => 'Edit Published Pages',
                    'publish_pages' => 'Publish Pages',
                    'delete_pages' => 'Delete Pages',
                    'delete_others_pages' => 'Delete Others Pages',
                    'delete_published_pages' => 'Delete Published Pages',
                    'delete_private_pages' => 'Delete Private Pages',
                    'edit_private_pages' => 'Edit Private Pages',
                    'read_private_pages' => 'Read Private Pages',
                )
            ),
            'media' => array(
                'label' => 'Media',
                'caps' => array(
                    'upload_files' => 'Upload Files',
                    'edit_files' => 'Edit Files',
                    'unfiltered_upload' => 'Unfiltered Upload',
                )
            ),
            'users' => array(
                'label' => 'Users',
                'caps' => array(
                    'list_users' => 'List Users',
                    'create_users' => 'Create Users',
                    'edit_users' => 'Edit Users',
                    'delete_users' => 'Delete Users',
                    'promote_users' => 'Promote Users',
                    'remove_users' => 'Remove Users',
                )
            ),
            'appearance' => array(
                'label' => 'Appearance',
                'caps' => array(
                    'switch_themes' => 'Switch Themes',
                    'edit_themes' => 'Edit Themes',
                    'edit_theme_options' => 'Edit Theme Options',
                    'install_themes' => 'Install Themes',
                    'update_themes' => 'Update Themes',
                    'delete_themes' => 'Delete Themes',
                )
            ),
            'plugins' => array(
                'label' => 'Plugins',
                'caps' => array(
                    'activate_plugins' => 'Activate Plugins',
                    'edit_plugins' => 'Edit Plugins',
                    'install_plugins' => 'Install Plugins',
                    'update_plugins' => 'Update Plugins',
                    'delete_plugins' => 'Delete Plugins',
                )
            ),
            'comments' => array(
                'label' => 'Comments',
                'caps' => array(
                    'moderate_comments' => 'Moderate Comments',
                    'edit_comment' => 'Edit Comment',
                )
            ),
            'core' => array(
                'label' => 'Core',
                'caps' => array(
                    'read' => 'Read',
                    'manage_options' => 'Manage Options',
                    'manage_categories' => 'Manage Categories',
                    'manage_links' => 'Manage Links',
                    'unfiltered_html' => 'Unfiltered HTML',
                    'import' => 'Import',
                    'export' => 'Export',
                    'update_core' => 'Update Core',
                )
            ),
        );

        return $caps;
    }

    /**
     * Get all roles
     */
    public static function get_all_roles() {
        global $wp_roles;
        
        $roles = array();
        $default_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
        
        foreach ( $wp_roles->roles as $key => $role ) {
            $roles[ $key ] = array(
                'name'       => $role['name'],
                'caps'       => array_keys( array_filter( $role['capabilities'] ) ),
                'user_count' => count( get_users( array( 'role' => $key ) ) ),
                'is_default' => in_array( $key, $default_roles ),
            );
        }
        
        return $roles;
    }

    /**
     * AJAX: Create new role
     */
    public function ajax_create_role() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $name = sanitize_text_field( $_POST['role_name'] ?? '' );
        $slug = sanitize_key( $_POST['role_slug'] ?? '' );
        $caps = isset( $_POST['capabilities'] ) ? json_decode( stripslashes( $_POST['capabilities'] ), true ) : array();
        
        if ( empty( $name ) || empty( $slug ) ) {
            wp_send_json_error( 'Name and slug are required' );
        }

        // Check if role exists
        if ( get_role( $slug ) ) {
            wp_send_json_error( 'Role already exists' );
        }

        // Sanitize capabilities
        $clean_caps = array();
        if ( is_array( $caps ) ) {
            foreach ( $caps as $cap ) {
                $clean_caps[ sanitize_key( $cap ) ] = true;
            }
        }

        add_role( $slug, $name, $clean_caps );
        
        wp_send_json_success( array( 'message' => 'Role created', 'slug' => $slug ) );
    }

    /**
     * AJAX: Update role capabilities
     */
    public function ajax_update_role() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $slug = sanitize_key( $_POST['role_slug'] ?? '' );
        $caps = isset( $_POST['capabilities'] ) ? json_decode( stripslashes( $_POST['capabilities'] ), true ) : array();
        
        if ( empty( $slug ) ) {
            wp_send_json_error( 'Role slug is required' );
        }

        // Prevent editing administrator
        if ( $slug === 'administrator' ) {
            wp_send_json_error( 'Cannot modify administrator role' );
        }

        $role = get_role( $slug );
        if ( ! $role ) {
            wp_send_json_error( 'Role not found' );
        }

        // Get all possible capabilities
        $all_caps = self::get_all_capabilities();
        $all_cap_keys = array();
        foreach ( $all_caps as $group ) {
            $all_cap_keys = array_merge( $all_cap_keys, array_keys( $group['caps'] ) );
        }

        // Remove all caps first
        foreach ( $all_cap_keys as $cap ) {
            $role->remove_cap( $cap );
        }

        // Add selected caps
        if ( is_array( $caps ) ) {
            foreach ( $caps as $cap ) {
                $role->add_cap( sanitize_key( $cap ) );
            }
        }
        
        wp_send_json_success( array( 'message' => 'Role updated' ) );
    }

    /**
     * AJAX: Delete role
     */
    public function ajax_delete_role() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $slug = sanitize_key( $_POST['role_slug'] ?? '' );
        
        $default_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
        if ( in_array( $slug, $default_roles ) ) {
            wp_send_json_error( 'Cannot delete default WordPress roles' );
        }

        // Check for users with this role
        $users = get_users( array( 'role' => $slug ) );
        if ( ! empty( $users ) ) {
            wp_send_json_error( 'Cannot delete role with assigned users. Reassign users first.' );
        }

        remove_role( $slug );
        
        wp_send_json_success( array( 'message' => 'Role deleted' ) );
    }

    /**
     * AJAX: Clone role
     */
    public function ajax_clone_role() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $source = sanitize_key( $_POST['source_role'] ?? '' );
        $name = sanitize_text_field( $_POST['new_name'] ?? '' );
        $slug = sanitize_key( $_POST['new_slug'] ?? '' );
        
        if ( empty( $source ) || empty( $name ) || empty( $slug ) ) {
            wp_send_json_error( 'All fields are required' );
        }

        if ( get_role( $slug ) ) {
            wp_send_json_error( 'Role already exists' );
        }

        $source_role = get_role( $source );
        if ( ! $source_role ) {
            wp_send_json_error( 'Source role not found' );
        }

        add_role( $slug, $name, $source_role->capabilities );
        
        wp_send_json_success( array( 'message' => 'Role cloned', 'slug' => $slug ) );
    }

    /**
     * AJAX: Get role capabilities
     */
    public function ajax_get_role_caps() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $slug = sanitize_key( $_POST['role_slug'] ?? '' );
        $role = get_role( $slug );
        
        if ( ! $role ) {
            wp_send_json_error( 'Role not found' );
        }

        wp_send_json_success( array(
            'caps' => array_keys( array_filter( $role->capabilities ) )
        ) );
    }

    /**
     * Render the role editor UI
     */
    public static function render_ui() {
        $roles = self::get_all_roles();
        $capabilities = self::get_all_capabilities();
        ?>
        <style>
        .wac-role-editor{background:#fff;border:1px solid #e5e5ea;border-radius:8px;overflow:hidden}
        .wac-role-toolbar{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e5ea;background:#f5f5f7}
        .wac-role-list{max-height:300px;overflow-y:auto}
        .wac-role-item{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e5ea;cursor:pointer}
        .wac-role-item:hover{background:#f9f9fb}
        .wac-role-item.selected{background:#f0f5ff;border-left:3px solid #007aff}
        .wac-role-item:last-child{border-bottom:none}
        .wac-role-info strong{font-size:14px;color:#1d1d1f}
        .wac-role-info small{font-size:11px;color:#86868b;margin-left:6px}
        .wac-role-meta{font-size:11px;color:#86868b}
        .wac-role-actions{display:flex;gap:8px}
        
        .wac-caps-editor{padding:16px;border-top:1px solid #e5e5ea;display:none}
        .wac-caps-editor.open{display:block}
        .wac-caps-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        .wac-caps-title{font-size:14px;font-weight:600}
        .wac-caps-groups{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
        .wac-caps-group{background:#f9f9fb;border-radius:6px;padding:12px}
        .wac-caps-group h4{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#86868b;margin:0 0 8px}
        .wac-caps-group label{display:flex;align-items:center;gap:6px;font-size:12px;padding:4px 0;cursor:pointer}
        .wac-caps-group input{margin:0;width:14px;height:14px;accent-color:#007aff}
        
        .wac-role-badge{font-size:10px;padding:2px 6px;border-radius:4px;background:#e5e5ea;color:#1d1d1f}
        .wac-role-badge.default{background:#007aff;color:#fff}
        </style>
        
        <div class="wac-role-editor">
            <div class="wac-role-toolbar">
                <div>
                    <span style="font-size:13px;color:#86868b"><?php echo count( $roles ); ?> roles</span>
                </div>
                <div style="display:flex;gap:8px">
                    <button type="button" class="wac-btn wac-btn-secondary" id="wac-clone-role">Clone Role</button>
                    <button type="button" class="wac-btn wac-btn-primary" id="wac-add-role">Add Role</button>
                </div>
            </div>
            
            <div class="wac-role-list">
                <?php foreach ( $roles as $slug => $role ) : ?>
                <div class="wac-role-item" data-slug="<?php echo esc_attr( $slug ); ?>">
                    <div class="wac-role-info">
                        <strong><?php echo esc_html( $role['name'] ); ?></strong>
                        <?php if ( $role['is_default'] ) : ?>
                            <span class="wac-role-badge default">Default</span>
                        <?php else : ?>
                            <span class="wac-role-badge">Custom</span>
                        <?php endif; ?>
                    </div>
                    <div class="wac-role-meta">
                        <?php echo $role['user_count']; ?> user(s) &middot; <?php echo count( $role['caps'] ); ?> caps
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="wac-caps-editor" id="wac-caps-editor">
                <div class="wac-caps-header">
                    <span class="wac-caps-title">Editing: <span id="wac-editing-role">-</span></span>
                    <div>
                        <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm" id="wac-select-all-caps">Select All</button>
                        <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm" id="wac-clear-all-caps">Clear All</button>
                        <button type="button" class="wac-btn wac-btn-primary" id="wac-save-caps">Save Changes</button>
                        <?php /* Only show delete for non-default roles */ ?>
                        <button type="button" class="wac-btn wac-btn-secondary" id="wac-delete-role" style="color:#ff3b30;display:none">Delete</button>
                    </div>
                </div>
                
                <div class="wac-caps-groups">
                    <?php foreach ( $capabilities as $group_key => $group ) : ?>
                    <div class="wac-caps-group">
                        <h4><?php echo esc_html( $group['label'] ); ?></h4>
                        <?php foreach ( $group['caps'] as $cap_key => $cap_label ) : ?>
                        <label>
                            <input type="checkbox" class="wac-cap-checkbox" value="<?php echo esc_attr( $cap_key ); ?>">
                            <?php echo esc_html( $cap_label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var currentRole = null;
            var rolesData = <?php echo json_encode( $roles ); ?>;
            var defaultRoles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
            
            // Select role
            $('.wac-role-item').on('click', function() {
                var slug = $(this).data('slug');
                
                if (slug === 'administrator') {
                    alert('Administrator role cannot be modified.');
                    return;
                }
                
                currentRole = slug;
                $('.wac-role-item').removeClass('selected');
                $(this).addClass('selected');
                
                $('#wac-editing-role').text(rolesData[slug].name);
                $('#wac-caps-editor').addClass('open');
                
                // Show delete button for custom roles
                if (defaultRoles.indexOf(slug) === -1) {
                    $('#wac-delete-role').show();
                } else {
                    $('#wac-delete-role').hide();
                }
                
                // Load capabilities
                loadRoleCaps(slug);
            });
            
            function loadRoleCaps(slug) {
                $.post(ajaxurl, {
                    action: 'wac_get_role_caps',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    role_slug: slug
                }, function(res) {
                    if (res.success) {
                        $('.wac-cap-checkbox').prop('checked', false);
                        res.data.caps.forEach(function(cap) {
                            $('.wac-cap-checkbox[value="' + cap + '"]').prop('checked', true);
                        });
                    }
                });
            }
            
            // Select/Clear all
            $('#wac-select-all-caps').on('click', function() {
                $('.wac-cap-checkbox').prop('checked', true);
            });
            
            $('#wac-clear-all-caps').on('click', function() {
                $('.wac-cap-checkbox').prop('checked', false);
            });
            
            // Save caps
            $('#wac-save-caps').on('click', function() {
                if (!currentRole) return;
                
                var caps = [];
                $('.wac-cap-checkbox:checked').each(function() {
                    caps.push($(this).val());
                });
                
                var $btn = $(this);
                $btn.text('Saving...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wac_update_role',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    role_slug: currentRole,
                    capabilities: JSON.stringify(caps)
                }, function(res) {
                    if (res.success) {
                        $btn.text('Saved!');
                        setTimeout(function() {
                            $btn.text('Save Changes').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert(res.data || 'Error saving');
                        $btn.text('Save Changes').prop('disabled', false);
                    }
                });
            });
            
            // Delete role
            $('#wac-delete-role').on('click', function() {
                if (!currentRole || defaultRoles.indexOf(currentRole) > -1) return;
                
                if (!confirm('Delete this role? This cannot be undone.')) return;
                
                $.post(ajaxurl, {
                    action: 'wac_delete_role',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    role_slug: currentRole
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error deleting role');
                    }
                });
            });
            
            // Add role
            $('#wac-add-role').on('click', function() {
                var name = prompt('Enter role name (display name):');
                if (!name) return;
                
                var slug = prompt('Enter role slug (lowercase, no spaces):');
                if (!slug) return;
                
                $.post(ajaxurl, {
                    action: 'wac_create_role',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    role_name: name,
                    role_slug: slug,
                    capabilities: JSON.stringify(['read'])
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error creating role');
                    }
                });
            });
            
            // Clone role
            $('#wac-clone-role').on('click', function() {
                var source = prompt('Enter source role slug to clone (e.g. editor):');
                if (!source) return;
                
                var name = prompt('Enter new role name:');
                if (!name) return;
                
                var slug = prompt('Enter new role slug:');
                if (!slug) return;
                
                $.post(ajaxurl, {
                    action: 'wac_clone_role',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    source_role: source,
                    new_name: name,
                    new_slug: slug
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data || 'Error cloning role');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

