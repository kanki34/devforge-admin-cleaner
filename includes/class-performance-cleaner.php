<?php
/**
 * Performance Cleaner - Database cleanup tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Performance_Cleaner {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wac_cleanup', array( $this, 'ajax_cleanup' ) );
    }

    /**
     * Get database stats
     */
    public function get_stats() {
        global $wpdb;

        return array(
            'revisions'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
            'drafts'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
            'trash'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
            'spam'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
            'trash_comments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
            'transients' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ),
            'orphan_meta'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})" ),
        );
    }

    /**
     * Get database size
     */
    public function get_db_size() {
        global $wpdb;
        $size = $wpdb->get_var( "SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'" );
        return $size ? size_format( $size ) : '0 B';
    }

    /**
     * Handle cleanup action
     */
    public function ajax_cleanup() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
        $result = 0;

        global $wpdb;

        switch ( $type ) {
            case 'revisions':
                $result = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
                break;
                
            case 'drafts':
                $result = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
                break;
                
            case 'trash':
                $posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
                foreach ( $posts as $id ) {
                    wp_delete_post( $id, true );
                    $result++;
                }
                break;
                
            case 'spam':
                $result = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
                break;

            case 'trash_comments':
                $result = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
                break;
                
            case 'transients':
                $result = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
                break;
                
            case 'orphan_meta':
                $result = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})" );
                $result += $wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})" );
                break;
                
            case 'optimize':
                $tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
                foreach ( $tables as $table ) {
                    $wpdb->query( "OPTIMIZE TABLE {$table[0]}" );
                    $result++;
                }
                break;

            case 'all':
                // Clean everything
                $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
                $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
                $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
                $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
                $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})" );
                $result = 1;
                break;
        }

        wp_send_json_success( array(
            'count' => $result,
            'stats' => $this->get_stats(),
            'db_size' => $this->get_db_size(),
        ) );
    }

    /**
     * Render UI
     */
    public static function render_ui() {
        $instance = self::get_instance();
        $stats = $instance->get_stats();
        $db_size = $instance->get_db_size();
        $total = array_sum( $stats );
        ?>
        
        <style>
        .wac-db-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e5e5ea}
        .wac-db-size{font-size:24px;font-weight:600}
        .wac-db-size small{font-size:12px;color:#86868b;font-weight:400;display:block}
        .wac-clean-all{background:#ff3b30!important;color:#fff!important;border:none!important}
        .wac-clean-all:hover{background:#d63029!important}
        .wac-cleanup-items{display:flex;flex-direction:column;gap:8px}
        .wac-cleanup-item{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f5f5f7;border-radius:8px}
        .wac-cleanup-item:hover{background:#ebebed}
        .wac-cleanup-info{flex:1}
        .wac-cleanup-info strong{font-size:14px;color:#1d1d1f;display:block;margin-bottom:2px}
        .wac-cleanup-info span{font-size:12px;color:#86868b}
        .wac-cleanup-count{min-width:40px;height:24px;background:#e5e5ea;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#1d1d1f;margin-right:12px}
        .wac-cleanup-count.has-items{background:#007aff;color:#fff}
        .wac-cleanup-btn{padding:6px 14px;background:#fff;border:1px solid #d1d1d6;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;color:#1d1d1f}
        .wac-cleanup-btn:hover{background:#1d1d1f;color:#fff;border-color:#1d1d1f}
        .wac-cleanup-btn:disabled{opacity:.5;cursor:not-allowed}
        .wac-cleanup-btn.cleaning{background:#007aff;color:#fff;border-color:#007aff}
        .wac-btn-accent{background:#007aff!important;color:#fff!important;border-color:#007aff!important}
        .wac-btn-accent:hover{background:#0056b3!important;border-color:#0056b3!important}
        .wac-optimize-item{background:#f0f5ff}
        .wac-progress-wrap{margin-top:20px;display:none}
        .wac-progress-bar{height:4px;background:#e5e5ea;border-radius:2px;overflow:hidden}
        .wac-progress-fill{height:100%;background:#34c759;width:0;transition:width .3s}
        .wac-progress-text{font-size:12px;color:#86868b;margin-top:8px;text-align:center}
        .wac-result-box{margin-top:16px;padding:12px 16px;border-radius:8px;font-size:13px;display:none}
        .wac-result-box.success{background:#d4edda;color:#155724;display:block}
        .wac-result-box.error{background:#f8d7da;color:#721c24;display:block}
        </style>
        
        <div class="wac-db-header">
            <div class="wac-db-size">
                <?php echo esc_html( $db_size ); ?>
                <small>Database Size</small>
            </div>
            <button type="button" class="button wac-clean-all" data-cleanup="all" <?php echo $total === 0 ? 'disabled' : ''; ?>>
                Clean All (<?php echo $total; ?> items)
            </button>
        </div>
        
        <div class="wac-cleanup-items">
            <?php
            $items = array(
                'revisions' => array(
                    'label' => 'Post Revisions',
                    'desc' => 'Old versions of posts saved automatically when editing',
                    'count' => $stats['revisions'],
                ),
                'drafts' => array(
                    'label' => 'Auto Drafts',
                    'desc' => 'Automatically saved drafts that were never published',
                    'count' => $stats['drafts'],
                ),
                'trash' => array(
                    'label' => 'Trashed Posts',
                    'desc' => 'Posts, pages, and other content in trash',
                    'count' => $stats['trash'],
                ),
                'spam' => array(
                    'label' => 'Spam Comments',
                    'desc' => 'Comments marked as spam',
                    'count' => $stats['spam'],
                ),
                'trash_comments' => array(
                    'label' => 'Trashed Comments',
                    'desc' => 'Comments in trash waiting to be deleted',
                    'count' => $stats['trash_comments'],
                ),
                'transients' => array(
                    'label' => 'Expired Transients',
                    'desc' => 'Temporary cached data that has expired',
                    'count' => $stats['transients'],
                ),
                'orphan_meta' => array(
                    'label' => 'Orphaned Data',
                    'desc' => 'Meta data without parent posts (leftover from deletions)',
                    'count' => $stats['orphan_meta'],
                ),
            );
            
            foreach ( $items as $key => $item ) :
                $has_items = $item['count'] > 0;
            ?>
                <div class="wac-cleanup-item">
                    <div class="wac-cleanup-info">
                        <strong><?php echo esc_html( $item['label'] ); ?></strong>
                        <span><?php echo esc_html( $item['desc'] ); ?></span>
                    </div>
                    <div class="wac-cleanup-count <?php echo $has_items ? 'has-items' : ''; ?>">
                        <?php echo $item['count']; ?>
                    </div>
                    <button type="button" class="wac-cleanup-btn" data-cleanup="<?php echo esc_attr( $key ); ?>" <?php echo ! $has_items ? 'disabled' : ''; ?>>
                        Clean
                    </button>
                </div>
            <?php endforeach; ?>
            
            <div class="wac-cleanup-item wac-optimize-item">
                <div class="wac-cleanup-info">
                    <strong>Optimize Tables</strong>
                    <span>Defragment and optimize all database tables</span>
                </div>
                <button type="button" class="wac-cleanup-btn wac-btn-accent" data-cleanup="optimize">
                    Optimize
                </button>
            </div>
        </div>
        
        <div class="wac-progress-wrap" id="wac-progress">
            <div class="wac-progress-bar">
                <div class="wac-progress-fill" id="wac-progress-fill"></div>
            </div>
            <div class="wac-progress-text" id="wac-progress-text">Cleaning...</div>
        </div>
        
        <div class="wac-result-box" id="wac-result"></div>
        
        <script>
        jQuery(function($) {
            function doCleanup(type, btn) {
                var originalText = btn.text();
                var progress = $('#wac-progress');
                var progressFill = $('#wac-progress-fill');
                var progressText = $('#wac-progress-text');
                var result = $('#wac-result');
                
                // Show progress
                progress.show();
                progressFill.css('width', '30%');
                progressText.text('Starting cleanup...');
                btn.addClass('cleaning').text('Cleaning...').prop('disabled', true);
                result.removeClass('success error').hide();
                
                // Simulate progress
                setTimeout(function() { progressFill.css('width', '60%'); progressText.text('Processing...'); }, 300);
                setTimeout(function() { progressFill.css('width', '80%'); progressText.text('Almost done...'); }, 600);
                
                $.ajax({
                    url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                    type: 'POST',
                    data: {
                        action: 'wac_cleanup',
                        type: type,
                        nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                    },
                    success: function(res) {
                        progressFill.css('width', '100%');
                        progressText.text('Complete!');
                        
                        setTimeout(function() {
                            progress.hide();
                            progressFill.css('width', '0');
                            
                            if (res.success) {
                                var msg = '';
                                if (type === 'all') {
                                    msg = 'All items cleaned successfully.';
                                } else if (type === 'optimize') {
                                    msg = res.data.count + ' tables optimized.';
                                } else {
                                    msg = res.data.count + ' items removed.';
                                }
                                result.addClass('success').html(msg).show();
                                
                                // Update counts
                                if (res.data.stats) {
                                    $.each(res.data.stats, function(key, val) {
                                        var countEl = $('[data-cleanup="' + key + '"]').siblings('.wac-cleanup-count');
                                        countEl.text(val);
                                        if (val === 0) {
                                            countEl.removeClass('has-items');
                                            $('[data-cleanup="' + key + '"]').prop('disabled', true);
                                        }
                                    });
                                    
                                    // Update total
                                    var total = 0;
                                    $.each(res.data.stats, function(k, v) { total += v; });
                                    $('.wac-clean-all').text('Clean All (' + total + ' items)');
                                    if (total === 0) $('.wac-clean-all').prop('disabled', true);
                                }
                                
                                // Update DB size
                                if (res.data.db_size) {
                                    $('.wac-db-size').html(res.data.db_size + '<small>Database Size</small>');
                                }
                            } else {
                                result.addClass('error').html('Error: ' + (res.data || 'Unknown error')).show();
                            }
                            
                            btn.removeClass('cleaning').text(originalText).prop('disabled', false);
                        }, 500);
                    },
                    error: function() {
                        progress.hide();
                        result.addClass('error').html('Connection error. Please try again.').show();
                        btn.removeClass('cleaning').text(originalText).prop('disabled', false);
                    }
                });
            }
            
            $('.wac-cleanup-btn, .wac-clean-all').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var type = btn.data('cleanup');
                
                if (type === 'all') {
                    if (!confirm('This will clean ALL items (revisions, drafts, trash, spam, transients, orphaned data). Continue?')) return;
                }
                
                doCleanup(type, btn);
            });
        });
        </script>
        <?php
    }
}
