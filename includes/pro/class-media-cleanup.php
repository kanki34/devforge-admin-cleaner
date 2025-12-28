<?php
/**
 * Media Cleanup
 * Find and delete unused media files
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Media_Cleanup {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wac_scan_media', array( $this, 'ajax_scan_media' ) );
        add_action( 'wp_ajax_wac_delete_unused_media', array( $this, 'ajax_delete_media' ) );
    }

    /**
     * Find unused media
     */
    public function find_unused_media() {
        global $wpdb;
        
        // Get all attachment IDs
        $attachments = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
        
        if ( empty( $attachments ) ) {
            return array();
        }

        $unused = array();
        
        foreach ( $attachments as $attachment_id ) {
            if ( $this->is_media_unused( $attachment_id ) ) {
                $unused[] = $attachment_id;
            }
        }

        return $unused;
    }

    /**
     * Check if media is unused
     */
    private function is_media_unused( $attachment_id ) {
        global $wpdb;

        // Check if used as featured image
        $featured = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
            $attachment_id
        ) );
        if ( $featured ) return false;

        // Get attachment URL and file path
        $url = wp_get_attachment_url( $attachment_id );
        $file = get_attached_file( $attachment_id );
        
        if ( ! $url ) return true;

        // Check in post content
        $found_in_content = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type NOT IN ('revision', 'attachment') LIMIT 1",
            '%' . $wpdb->esc_like( basename( $url ) ) . '%'
        ) );
        if ( $found_in_content ) return false;

        // Check in post meta (ACF, etc.)
        $found_in_meta = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like( basename( $url ) ) . '%'
        ) );
        if ( $found_in_meta ) return false;

        // Check in options (widgets, theme settings)
        $found_in_options = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_id FROM {$wpdb->options} WHERE option_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like( basename( $url ) ) . '%'
        ) );
        if ( $found_in_options ) return false;

        // Check WooCommerce galleries
        $found_in_gallery = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s LIMIT 1",
            '%' . $attachment_id . '%'
        ) );
        if ( $found_in_gallery ) return false;

        return true;
    }

    /**
     * AJAX: Scan media
     */
    public function ajax_scan_media() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        // Set time limit for large libraries
        set_time_limit( 300 );

        $unused = $this->find_unused_media();
        $items = array();
        $total_size = 0;

        foreach ( array_slice( $unused, 0, 100 ) as $id ) {
            $file = get_attached_file( $id );
            $size = $file && file_exists( $file ) ? filesize( $file ) : 0;
            $total_size += $size;
            
            $items[] = array(
                'id' => $id,
                'title' => get_the_title( $id ),
                'thumbnail' => wp_get_attachment_image_url( $id, 'thumbnail' ),
                'type' => get_post_mime_type( $id ),
                'size' => size_format( $size ),
                'date' => get_the_date( 'Y-m-d', $id ),
            );
        }

        wp_send_json_success( array(
            'total' => count( $unused ),
            'total_size' => size_format( $total_size ),
            'items' => $items,
        ) );
    }

    /**
     * AJAX: Delete unused media
     */
    public function ajax_delete_media() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $ids = isset( $_POST['ids'] ) ? json_decode( stripslashes( $_POST['ids'] ), true ) : array();
        
        if ( empty( $ids ) || ! is_array( $ids ) ) {
            wp_send_json_error( 'No items selected' );
        }

        $deleted = 0;
        $freed_size = 0;

        foreach ( $ids as $id ) {
            $id = intval( $id );
            
            // Double check it's actually unused
            if ( ! $this->is_media_unused( $id ) ) {
                continue;
            }

            $file = get_attached_file( $id );
            if ( $file && file_exists( $file ) ) {
                $freed_size += filesize( $file );
            }

            if ( wp_delete_attachment( $id, true ) ) {
                $deleted++;
            }
        }

        wp_send_json_success( array(
            'deleted' => $deleted,
            'freed_size' => size_format( $freed_size ),
        ) );
    }

    /**
     * Render the media cleanup UI
     */
    public static function render_ui() {
        ?>
        <style>
        .wac-media-cleanup{background:#f9f9fb;border-radius:8px;padding:20px}
        .wac-media-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
        .wac-media-stat{background:#fff;padding:16px;border-radius:8px;text-align:center;border:1px solid #e5e5ea}
        .wac-media-stat-value{font-size:28px;font-weight:600;color:#1d1d1f}
        .wac-media-stat-label{font-size:12px;color:#86868b;text-transform:uppercase;margin-top:4px}
        .wac-media-actions{margin-bottom:20px;display:flex;gap:8px}
        .wac-media-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;max-height:300px;overflow-y:auto}
        .wac-media-item{position:relative;aspect-ratio:1;background:#e5e5ea;border-radius:6px;overflow:hidden;cursor:pointer}
        .wac-media-item img{width:100%;height:100%;object-fit:cover}
        .wac-media-item.selected{outline:3px solid #007aff;outline-offset:-3px}
        .wac-media-item .wac-media-check{position:absolute;top:4px;right:4px;width:20px;height:20px;background:#007aff;border-radius:50%;display:none;align-items:center;justify-content:center}
        .wac-media-item.selected .wac-media-check{display:flex}
        .wac-media-item .wac-media-check .dashicons{font-size:14px;width:14px;height:14px;color:#fff}
        .wac-media-item .wac-media-size{position:absolute;bottom:0;left:0;right:0;padding:4px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;text-align:center}
        .wac-media-empty{text-align:center;padding:40px;color:#86868b}
        .wac-media-scanning{text-align:center;padding:40px}
        .wac-media-result{padding:12px;background:#d4edda;color:#155724;border-radius:6px;margin-bottom:16px;display:none}
        .wac-media-file-icon{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f5f5f7}
        .wac-media-file-icon .dashicons{font-size:32px;width:32px;height:32px;color:#86868b}
        </style>
        
        <div class="wac-media-cleanup">
            <div class="wac-media-stats">
                <div class="wac-media-stat">
                    <div class="wac-media-stat-value" id="wac-unused-count">-</div>
                    <div class="wac-media-stat-label">Unused Files</div>
                </div>
                <div class="wac-media-stat">
                    <div class="wac-media-stat-value" id="wac-unused-size">-</div>
                    <div class="wac-media-stat-label">Total Size</div>
                </div>
                <div class="wac-media-stat">
                    <div class="wac-media-stat-value" id="wac-selected-count">0</div>
                    <div class="wac-media-stat-label">Selected</div>
                </div>
            </div>
            
            <div class="wac-media-result" id="wac-media-result"></div>
            
            <div class="wac-media-actions">
                <button type="button" class="wac-btn wac-btn-primary" id="wac-scan-media">Scan Media Library</button>
                <button type="button" class="wac-btn wac-btn-secondary" id="wac-select-all-media" style="display:none">Select All</button>
                <button type="button" class="wac-btn wac-btn-secondary" id="wac-deselect-all-media" style="display:none">Deselect All</button>
                <button type="button" class="wac-btn wac-btn-danger" id="wac-delete-media" style="display:none">Delete Selected</button>
            </div>
            
            <div id="wac-media-container">
                <div class="wac-media-empty">Click "Scan Media Library" to find unused files.</div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var selectedIds = [];
            var allItems = [];
            
            function updateSelectedCount() {
                $('#wac-selected-count').text(selectedIds.length);
                $('#wac-delete-media').toggle(selectedIds.length > 0);
            }
            
            // Scan
            $('#wac-scan-media').on('click', function() {
                var $btn = $(this);
                var $container = $('#wac-media-container');
                
                $btn.prop('disabled', true).text('Scanning...');
                $container.html('<div class="wac-media-scanning"><span class="spinner is-active" style="float:none;margin:0"></span><p>Scanning media library... This may take a while.</p></div>');
                selectedIds = [];
                updateSelectedCount();
                
                $.post(ajaxurl, {
                    action: 'wac_scan_media',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                }, function(res) {
                    $btn.prop('disabled', false).text('Scan Again');
                    
                    if (res.success) {
                        $('#wac-unused-count').text(res.data.total);
                        $('#wac-unused-size').text(res.data.total_size);
                        allItems = res.data.items;
                        
                        if (res.data.items.length) {
                            var html = '<div class="wac-media-grid">';
                            res.data.items.forEach(function(item) {
                                var preview = item.thumbnail 
                                    ? '<img src="' + item.thumbnail + '" alt="">'
                                    : '<div class="wac-media-file-icon"><span class="dashicons dashicons-media-default"></span></div>';
                                html += '<div class="wac-media-item" data-id="' + item.id + '">';
                                html += preview;
                                html += '<div class="wac-media-check"><span class="dashicons dashicons-yes"></span></div>';
                                html += '<div class="wac-media-size">' + item.size + '</div>';
                                html += '</div>';
                            });
                            html += '</div>';
                            if (res.data.total > 100) {
                                html += '<p style="text-align:center;margin-top:12px;color:#86868b">Showing first 100 of ' + res.data.total + ' unused files.</p>';
                            }
                            $container.html(html);
                            $('#wac-select-all-media, #wac-deselect-all-media').show();
                        } else {
                            $container.html('<div class="wac-media-empty">No unused media files found. Your library is clean!</div>');
                            $('#wac-select-all-media, #wac-deselect-all-media').hide();
                        }
                    } else {
                        $container.html('<div class="wac-media-empty">Error scanning media library.</div>');
                    }
                });
            });
            
            // Select item
            $(document).on('click', '.wac-media-item', function() {
                var id = $(this).data('id');
                var idx = selectedIds.indexOf(id);
                
                if (idx > -1) {
                    selectedIds.splice(idx, 1);
                    $(this).removeClass('selected');
                } else {
                    selectedIds.push(id);
                    $(this).addClass('selected');
                }
                
                updateSelectedCount();
            });
            
            // Select all
            $('#wac-select-all-media').on('click', function() {
                selectedIds = allItems.map(function(i) { return i.id; });
                $('.wac-media-item').addClass('selected');
                updateSelectedCount();
            });
            
            // Deselect all
            $('#wac-deselect-all-media').on('click', function() {
                selectedIds = [];
                $('.wac-media-item').removeClass('selected');
                updateSelectedCount();
            });
            
            // Delete
            $('#wac-delete-media').on('click', function() {
                if (!selectedIds.length) return;
                
                if (!confirm('Permanently delete ' + selectedIds.length + ' file(s)? This cannot be undone.')) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Deleting...');
                
                $.post(ajaxurl, {
                    action: 'wac_delete_unused_media',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    ids: JSON.stringify(selectedIds)
                }, function(res) {
                    $btn.prop('disabled', false).text('Delete Selected');
                    
                    if (res.success) {
                        $('#wac-media-result').html('Deleted ' + res.data.deleted + ' files. Freed ' + res.data.freed_size + '.').show();
                        // Remove deleted items
                        selectedIds.forEach(function(id) {
                            $('.wac-media-item[data-id="' + id + '"]').fadeOut(function() { $(this).remove(); });
                        });
                        selectedIds = [];
                        updateSelectedCount();
                        
                        setTimeout(function() { $('#wac-media-result').fadeOut(); }, 5000);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

