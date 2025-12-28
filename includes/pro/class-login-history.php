<?php
/**
 * Login History
 * Track and display user login history with details
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WAC_Login_History {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wac_login_history';
        
        // Create table on activation
        add_action( 'admin_init', array( $this, 'create_table' ) );
        
        // Track logins
        add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
        add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
        add_action( 'clear_auth_cookie', array( $this, 'log_logout' ) );
        
        // AJAX
        add_action( 'wp_ajax_wac_get_login_history', array( $this, 'ajax_get_history' ) );
        add_action( 'wp_ajax_wac_clear_login_history', array( $this, 'ajax_clear_history' ) );
    }

    /**
     * Create database table
     */
    public function create_table() {
        global $wpdb;
        
        if ( get_option( 'wac_login_history_table_version' ) === '1.0' ) {
            return;
        }

        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            username varchar(100) NOT NULL,
            event varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            browser varchar(100),
            os varchar(100),
            country varchar(100),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event (event),
            KEY created_at (created_at)
        ) $charset;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        update_option( 'wac_login_history_table_version', '1.0' );
    }

    /**
     * Log successful login
     */
    public function log_login( $user_login, $user ) {
        $this->add_log( $user->ID, $user_login, 'login' );
    }

    /**
     * Log failed login
     */
    public function log_failed_login( $username ) {
        $this->add_log( null, $username, 'failed' );
    }

    /**
     * Log logout
     */
    public function log_logout() {
        $user = wp_get_current_user();
        if ( $user->ID ) {
            $this->add_log( $user->ID, $user->user_login, 'logout' );
        }
    }

    /**
     * Add log entry
     */
    private function add_log( $user_id, $username, $event ) {
        global $wpdb;
        
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $browser = $this->parse_browser( $user_agent );
        $os = $this->parse_os( $user_agent );
        $ip = $this->get_ip();
        
        $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'username' => $username,
                'event' => $event,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'browser' => $browser,
                'os' => $os,
                'country' => '', // Could add GeoIP lookup
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        
        // Clean old entries (keep last 1000)
        $wpdb->query( "DELETE FROM {$this->table_name} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$this->table_name} ORDER BY id DESC LIMIT 1000) AS t)" );
    }

    /**
     * Get client IP
     */
    private function get_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
        } elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field( trim( $ip ) );
    }

    /**
     * Parse browser from user agent
     */
    private function parse_browser( $user_agent ) {
        $browsers = array(
            'Edge' => '/Edge\/([0-9.]+)/',
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Safari\/([0-9.]+)/',
            'Opera' => '/OPR\/([0-9.]+)/',
            'IE' => '/MSIE ([0-9.]+)/',
        );
        
        foreach ( $browsers as $name => $pattern ) {
            if ( preg_match( $pattern, $user_agent, $matches ) ) {
                return $name . ' ' . intval( $matches[1] );
            }
        }
        
        return 'Unknown';
    }

    /**
     * Parse OS from user agent
     */
    private function parse_os( $user_agent ) {
        $os_list = array(
            'Windows 11' => '/Windows NT 10.0.*Win64/',
            'Windows 10' => '/Windows NT 10.0/',
            'Windows 8' => '/Windows NT 6.2/',
            'Windows 7' => '/Windows NT 6.1/',
            'macOS' => '/Mac OS X/',
            'iOS' => '/iPhone|iPad/',
            'Android' => '/Android/',
            'Linux' => '/Linux/',
        );
        
        foreach ( $os_list as $name => $pattern ) {
            if ( preg_match( $pattern, $user_agent ) ) {
                return $name;
            }
        }
        
        return 'Unknown';
    }

    /**
     * AJAX: Get login history
     */
    public function ajax_get_history() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $per_page = 20;
        $offset = ( $page - 1 ) * $per_page;
        
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );
        
        $items = array();
        foreach ( $logs as $log ) {
            $items[] = array(
                'id' => $log->id,
                'user_id' => $log->user_id,
                'username' => $log->username,
                'event' => $log->event,
                'ip' => $log->ip_address,
                'browser' => $log->browser,
                'os' => $log->os,
                'time' => human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) . ' ago',
                'date' => $log->created_at,
            );
        }
        
        wp_send_json_success( array(
            'items' => $items,
            'total' => intval( $total ),
            'pages' => ceil( $total / $per_page ),
            'current_page' => $page,
        ) );
    }

    /**
     * AJAX: Clear history
     */
    public function ajax_clear_history() {
        check_ajax_referer( 'wac_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
        
        wp_send_json_success();
    }

    /**
     * Get stats
     */
    public static function get_stats() {
        global $wpdb;
        $instance = self::get_instance();
        
        $today = current_time( 'Y-m-d' );
        $week = date( 'Y-m-d', strtotime( '-7 days' ) );
        
        return array(
            'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$instance->table_name}" ),
            'today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$instance->table_name} WHERE DATE(created_at) = %s AND event = 'login'", $today ) ),
            'failed_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$instance->table_name} WHERE DATE(created_at) = %s AND event = 'failed'", $today ) ),
            'week' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$instance->table_name} WHERE created_at >= %s AND event = 'login'", $week ) ),
        );
    }

    /**
     * Render UI
     */
    public static function render_ui() {
        $stats = self::get_stats();
        ?>
        <style>
        .wac-login-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
        .wac-login-stat{background:#f9f9fb;padding:16px;border-radius:8px;text-align:center}
        .wac-login-stat-value{font-size:24px;font-weight:600;color:#1d1d1f}
        .wac-login-stat-label{font-size:11px;color:#86868b;text-transform:uppercase;margin-top:4px}
        .wac-login-stat.failed .wac-login-stat-value{color:#ff3b30}
        .wac-login-table{width:100%;border-collapse:collapse}
        .wac-login-table th,.wac-login-table td{padding:10px 12px;text-align:left;border-bottom:1px solid #e5e5ea;font-size:13px}
        .wac-login-table th{background:#f5f5f7;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#86868b}
        .wac-login-table tr:hover{background:#f9f9fb}
        .wac-login-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500}
        .wac-login-badge.login{background:#d4edda;color:#155724}
        .wac-login-badge.logout{background:#e5e5ea;color:#1d1d1f}
        .wac-login-badge.failed{background:#f8d7da;color:#721c24}
        .wac-login-pagination{display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e5e5ea}
        </style>
        
        <div class="wac-login-history">
            <div class="wac-login-stats">
                <div class="wac-login-stat">
                    <div class="wac-login-stat-value"><?php echo $stats['today']; ?></div>
                    <div class="wac-login-stat-label">Logins Today</div>
                </div>
                <div class="wac-login-stat failed">
                    <div class="wac-login-stat-value"><?php echo $stats['failed_today']; ?></div>
                    <div class="wac-login-stat-label">Failed Today</div>
                </div>
                <div class="wac-login-stat">
                    <div class="wac-login-stat-value"><?php echo $stats['week']; ?></div>
                    <div class="wac-login-stat-label">This Week</div>
                </div>
                <div class="wac-login-stat">
                    <div class="wac-login-stat-value"><?php echo $stats['total']; ?></div>
                    <div class="wac-login-stat-label">Total Records</div>
                </div>
            </div>
            
            <div style="display:flex;justify-content:space-between;margin-bottom:12px">
                <button type="button" class="wac-btn wac-btn-secondary" id="wac-refresh-history">Refresh</button>
                <button type="button" class="wac-btn wac-btn-secondary" id="wac-clear-history" style="color:#ff3b30">Clear All</button>
            </div>
            
            <div id="wac-login-history-table">
                <table class="wac-login-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Event</th>
                            <th>IP Address</th>
                            <th>Browser</th>
                            <th>OS</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody id="wac-login-history-body">
                        <tr><td colspan="6" style="text-align:center;color:#86868b;padding:20px">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div class="wac-login-pagination">
                <span id="wac-history-info"></span>
                <div>
                    <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm" id="wac-history-prev" disabled>Previous</button>
                    <button type="button" class="wac-btn wac-btn-secondary wac-btn-sm" id="wac-history-next">Next</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            var currentPage = 1;
            var totalPages = 1;
            
            function loadHistory(page) {
                page = page || 1;
                
                $.post(ajaxurl, {
                    action: 'wac_get_login_history',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>',
                    page: page
                }, function(res) {
                    if (res.success) {
                        currentPage = res.data.current_page;
                        totalPages = res.data.pages;
                        
                        var html = '';
                        if (res.data.items.length) {
                            res.data.items.forEach(function(item) {
                                var badge = '<span class="wac-login-badge ' + item.event + '">' + item.event + '</span>';
                                html += '<tr>';
                                html += '<td><strong>' + escHtml(item.username) + '</strong></td>';
                                html += '<td>' + badge + '</td>';
                                html += '<td><code>' + escHtml(item.ip) + '</code></td>';
                                html += '<td>' + escHtml(item.browser) + '</td>';
                                html += '<td>' + escHtml(item.os) + '</td>';
                                html += '<td>' + escHtml(item.time) + '</td>';
                                html += '</tr>';
                            });
                        } else {
                            html = '<tr><td colspan="6" style="text-align:center;color:#86868b;padding:20px">No login history yet.</td></tr>';
                        }
                        
                        $('#wac-login-history-body').html(html);
                        $('#wac-history-info').text('Page ' + currentPage + ' of ' + totalPages + ' (' + res.data.total + ' records)');
                        $('#wac-history-prev').prop('disabled', currentPage <= 1);
                        $('#wac-history-next').prop('disabled', currentPage >= totalPages);
                    }
                });
            }
            
            function escHtml(str) {
                if (!str) return '';
                return $('<div>').text(str).html();
            }
            
            // Initial load
            loadHistory(1);
            
            // Pagination
            $('#wac-history-prev').on('click', function() {
                if (currentPage > 1) loadHistory(currentPage - 1);
            });
            
            $('#wac-history-next').on('click', function() {
                if (currentPage < totalPages) loadHistory(currentPage + 1);
            });
            
            // Refresh
            $('#wac-refresh-history').on('click', function() {
                loadHistory(currentPage);
            });
            
            // Clear
            $('#wac-clear-history').on('click', function() {
                if (!confirm('Clear all login history? This cannot be undone.')) return;
                
                $.post(ajaxurl, {
                    action: 'wac_clear_login_history',
                    nonce: '<?php echo wp_create_nonce( 'wac_admin_nonce' ); ?>'
                }, function() {
                    loadHistory(1);
                });
            });
        });
        </script>
        <?php
    }
}

