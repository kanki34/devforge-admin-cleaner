<?php
/**
 * Custom Admin Columns
 * Add custom columns to post lists (Featured Image, Word Count, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Admin_Columns {

    private static $instance = null;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'wac_admin_columns', array() );
        
        // Register columns for each post type
        add_action( 'admin_init', array( $this, 'register_columns' ) );
        add_action( 'wp_ajax_wac_save_columns', array( $this, 'ajax_save_columns' ) );
    }

    /**
     * Get available column types
     */
    public static function get_column_types() {
        return array(
            'thumbnail' => array(
                'label' => 'Featured Image',
                'description' => 'Show post thumbnail',
            ),
            'id' => array(
                'label' => 'Post ID',
                'description' => 'Display the post ID',
            ),
            'word_count' => array(
                'label' => 'Word Count',
                'description' => 'Content word count',
            ),
            'modified' => array(
                'label' => 'Last Modified',
                'description' => 'Last modification date',
            ),
            'template' => array(
                'label' => 'Page Template',
                'description' => 'Page template name (pages only)',
            ),
            'slug' => array(
                'label' => 'Slug',
                'description' => 'Post URL slug',
            ),
            'status' => array(
                'label' => 'Status',
                'description' => 'Post status with color',
            ),
            'custom_field' => array(
                'label' => 'Custom Field',
                'description' => 'Display a custom field value',
            ),
        );
    }

    /**
     * Get post types for column management
     */
    public static function get_post_types() {
        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
        $excluded = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
        
        $result = array();
        foreach ( $post_types as $type => $obj ) {
            if ( in_array( $type, $excluded ) ) continue;
            $result[ $type ] = $obj->labels->name;
        }
        
        return $result;
    }

    /**
     * Register columns for enabled post types
     */
    public function register_columns() {
        if ( empty( $this->options ) ) return;

        foreach ( $this->options as $post_type => $columns ) {
            if ( empty( $columns ) || ! is_array( $columns ) ) continue;

            // Add column headers
            add_filter( "manage_{$post_type}_posts_columns", function( $cols ) use ( $columns, $post_type ) {
                return $this->add_column_headers( $cols, $columns, $post_type );
            }, 100 );

            // Add column content
            add_action( "manage_{$post_type}_posts_custom_column", function( $column, $post_id ) use ( $columns ) {
                $this->render_column_content( $column, $post_id, $columns );
            }, 10, 2 );

            // Make sortable
            add_filter( "manage_edit-{$post_type}_sortable_columns", function( $cols ) use ( $columns ) {
                foreach ( $columns as $col ) {
                    if ( ! empty( $col['enabled'] ) && in_array( $col['type'], array( 'id', 'modified', 'word_count' ) ) ) {
                        $cols[ 'wac_' . $col['type'] ] = 'wac_' . $col['type'];
                    }
                }
                return $cols;
            } );
        }

        // Handle sorting
        add_action( 'pre_get_posts', array( $this, 'handle_sorting' ) );
    }

    /**
     * Add column headers
     */
    private function add_column_headers( $cols, $columns, $post_type ) {
        $new_cols = array();
        
        foreach ( $cols as $key => $label ) {
            $new_cols[ $key ] = $label;
            
            // Add after title
            if ( $key === 'title' ) {
                foreach ( $columns as $col ) {
                    if ( ! empty( $col['enabled'] ) ) {
                        $col_key = 'wac_' . $col['type'];
                        if ( $col['type'] === 'custom_field' && ! empty( $col['meta_key'] ) ) {
                            $col_key .= '_' . sanitize_key( $col['meta_key'] );
                        }
                        $new_cols[ $col_key ] = $col['label'] ?? ucfirst( str_replace( '_', ' ', $col['type'] ) );
                    }
                }
            }
        }
        
        return $new_cols;
    }

    /**
     * Render column content
     */
    private function render_column_content( $column, $post_id, $columns ) {
        foreach ( $columns as $col ) {
            if ( empty( $col['enabled'] ) ) continue;
            
            $col_key = 'wac_' . $col['type'];
            if ( $col['type'] === 'custom_field' && ! empty( $col['meta_key'] ) ) {
                $col_key .= '_' . sanitize_key( $col['meta_key'] );
            }
            
            if ( $column !== $col_key ) continue;
            
            switch ( $col['type'] ) {
                case 'thumbnail':
                    if ( has_post_thumbnail( $post_id ) ) {
                        echo get_the_post_thumbnail( $post_id, array( 50, 50 ), array( 'style' => 'border-radius:4px' ) );
                    } else {
                        echo '<span style="color:#999">—</span>';
                    }
                    break;

                case 'id':
                    echo '<code>' . $post_id . '</code>';
                    break;

                case 'word_count':
                    $content = get_post_field( 'post_content', $post_id );
                    $count = str_word_count( strip_tags( $content ) );
                    echo number_format_i18n( $count );
                    break;

                case 'modified':
                    echo get_the_modified_date( 'Y/m/d', $post_id );
                    echo '<br><small style="color:#999">' . get_the_modified_time( 'H:i', $post_id ) . '</small>';
                    break;

                case 'template':
                    $template = get_page_template_slug( $post_id );
                    if ( $template ) {
                        echo '<code style="font-size:11px">' . basename( $template ) . '</code>';
                    } else {
                        echo '<span style="color:#999">Default</span>';
                    }
                    break;

                case 'slug':
                    $slug = get_post_field( 'post_name', $post_id );
                    echo '<code style="font-size:11px">' . esc_html( $slug ) . '</code>';
                    break;

                case 'status':
                    $status = get_post_status( $post_id );
                    $colors = array(
                        'publish' => '#34c759',
                        'draft' => '#ff9500',
                        'pending' => '#007aff',
                        'private' => '#5856d6',
                        'future' => '#af52de',
                        'trash' => '#ff3b30',
                    );
                    $color = $colors[ $status ] ?? '#999';
                    echo '<span style="display:inline-block;padding:2px 8px;background:' . $color . '15;color:' . $color . ';border-radius:4px;font-size:11px;font-weight:500">' . ucfirst( $status ) . '</span>';
                    break;

                case 'custom_field':
                    if ( ! empty( $col['meta_key'] ) ) {
                        $value = get_post_meta( $post_id, $col['meta_key'], true );
                        if ( $value ) {
                            if ( is_array( $value ) ) {
                                echo '<code style="font-size:11px">' . esc_html( implode( ', ', $value ) ) . '</code>';
                            } else {
                                echo esc_html( $value );
                            }
                        } else {
                            echo '<span style="color:#999">—</span>';
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Handle column sorting
     */
    public function handle_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        
        $orderby = $query->get( 'orderby' );
        
        if ( $orderby === 'wac_id' ) {
            $query->set( 'orderby', 'ID' );
        } elseif ( $orderby === 'wac_modified' ) {
            $query->set( 'orderby', 'modified' );
        } elseif ( $orderby === 'wac_word_count' ) {
            // Word count sorting would require meta - skip for now
            $query->set( 'orderby', 'date' );
        }
    }

    /**
     * AJAX: Save columns
     */
    public function ajax_save_columns() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $data = isset( $_POST['columns_data'] ) ? json_decode( stripslashes( $_POST['columns_data'] ), true ) : array();
        
        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        // Sanitize
        $clean = array();
        foreach ( $data as $post_type => $columns ) {
            $post_type = sanitize_key( $post_type );
            $clean[ $post_type ] = array();
            
            if ( is_array( $columns ) ) {
                foreach ( $columns as $col ) {
                    $clean[ $post_type ][] = array(
                        'type'     => sanitize_key( $col['type'] ?? 'id' ),
                        'label'    => sanitize_text_field( $col['label'] ?? '' ),
                        'enabled'  => ! empty( $col['enabled'] ),
                        'meta_key' => sanitize_key( $col['meta_key'] ?? '' ),
                    );
                }
            }
        }
        
        update_option( 'wac_admin_columns', $clean );
        
        wp_send_json_success( array( 'message' => 'Columns saved' ) );
    }

    /**
     * Render columns settings UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        $options = $instance->options;
        $post_types = self::get_post_types();
        $column_types = self::get_column_types();
        ?>
        <style>
        .wac-columns-builder{background:#fff;border:1px solid #e5e5ea;border-radius:8px;overflow:hidden}
        .wac-columns-tabs{display:flex;border-bottom:1px solid #e5e5ea;background:#f5f5f7}
        .wac-columns-tab{padding:12px 20px;font-size:13px;font-weight:500;color:#86868b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px}
        .wac-columns-tab:hover{color:#1d1d1f}
        .wac-columns-tab.active{color:#007aff;border-bottom-color:#007aff}
        .wac-columns-content{padding:20px}
        .wac-columns-panel{display:none}
        .wac-columns-panel.active{display:block}
        
        .wac-column-list{display:flex;flex-direction:column;gap:12px}
        .wac-column-item{display:flex;align-items:center;gap:12px;padding:12px;background:#f9f9fb;border-radius:8px}
        .wac-column-item label{display:flex;align-items:center;gap:8px;flex:1}
        .wac-column-item input[type=text]{padding:6px 10px;border:1px solid #d1d1d6;border-radius:4px;font-size:12px}
        .wac-column-item select{padding:6px 10px;border:1px solid #d1d1d6;border-radius:4px;font-size:12px}
        .wac-column-check{width:18px;height:18px;accent-color:#007aff}
        .wac-column-type{font-size:13px;font-weight:500}
        .wac-column-desc{font-size:11px;color:#86868b}
        .wac-column-meta{margin-left:auto;display:flex;align-items:center;gap:8px}
        
        .wac-save-columns{margin-top:16px;display:flex;align-items:center;gap:12px}
        </style>
        
        <div class="wac-columns-builder">
            <div class="wac-columns-tabs">
                <?php $first = true; foreach ( $post_types as $type => $label ) : ?>
                <div class="wac-columns-tab <?php echo $first ? 'active' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>">
                    <?php echo esc_html( $label ); ?>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
            
            <div class="wac-columns-content">
                <?php $first = true; foreach ( $post_types as $type => $label ) : 
                    $type_columns = $options[ $type ] ?? array();
                    // Create a map of enabled columns
                    $enabled_map = array();
                    foreach ( $type_columns as $col ) {
                        $enabled_map[ $col['type'] ] = $col;
                    }
                ?>
                <div class="wac-columns-panel <?php echo $first ? 'active' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>">
                    <div class="wac-column-list">
                        <?php foreach ( $column_types as $col_type => $col_info ) : 
                            // Skip template for non-page types
                            if ( $col_type === 'template' && $type !== 'page' ) continue;
                            
                            $is_enabled = isset( $enabled_map[ $col_type ] ) && ! empty( $enabled_map[ $col_type ]['enabled'] );
                            $custom_label = isset( $enabled_map[ $col_type ] ) ? ( $enabled_map[ $col_type ]['label'] ?? '' ) : '';
                            $meta_key = isset( $enabled_map[ $col_type ] ) ? ( $enabled_map[ $col_type ]['meta_key'] ?? '' ) : '';
                        ?>
                        <div class="wac-column-item">
                            <label>
                                <input type="checkbox" class="wac-column-check" data-type="<?php echo esc_attr( $col_type ); ?>" <?php checked( $is_enabled ); ?>>
                                <div>
                                    <div class="wac-column-type"><?php echo esc_html( $col_info['label'] ); ?></div>
                                    <div class="wac-column-desc"><?php echo esc_html( $col_info['description'] ); ?></div>
                                </div>
                            </label>
                            <div class="wac-column-meta">
                                <input type="text" class="wac-column-label" placeholder="Column label" value="<?php echo esc_attr( $custom_label ); ?>" style="width:120px">
                                <?php if ( $col_type === 'custom_field' ) : ?>
                                <input type="text" class="wac-column-meta-key" placeholder="Meta key" value="<?php echo esc_attr( $meta_key ); ?>" style="width:100px">
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
                
                <div class="wac-save-columns">
                    <button type="button" class="wac-btn wac-btn-primary" id="wac-save-columns">Save Columns</button>
                    <span id="wac-columns-status" style="font-size:12px;color:#86868b"></span>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            // Tab switching
            $('.wac-columns-tab').on('click', function() {
                var type = $(this).data('type');
                $('.wac-columns-tab').removeClass('active');
                $(this).addClass('active');
                $('.wac-columns-panel').removeClass('active');
                $('.wac-columns-panel[data-type="' + type + '"]').addClass('active');
            });
            
            // Save columns
            $('#wac-save-columns').on('click', function() {
                var $btn = $(this);
                var $status = $('#wac-columns-status');
                var data = {};
                
                $('.wac-columns-panel').each(function() {
                    var type = $(this).data('type');
                    data[type] = [];
                    
                    $(this).find('.wac-column-item').each(function() {
                        var $item = $(this);
                        var colType = $item.find('.wac-column-check').data('type');
                        var enabled = $item.find('.wac-column-check').is(':checked');
                        var label = $item.find('.wac-column-label').val();
                        var metaKey = $item.find('.wac-column-meta-key').val() || '';
                        
                        data[type].push({
                            type: colType,
                            enabled: enabled,
                            label: label,
                            meta_key: metaKey
                        });
                    });
                });
                
                $btn.text('Saving...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wac_save_columns',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    columns_data: JSON.stringify(data)
                }, function(res) {
                    if (res.success) {
                        $status.text('Saved! Refresh to see changes.');
                    } else {
                        $status.text('Error saving');
                    }
                    $btn.text('Save Columns').prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

