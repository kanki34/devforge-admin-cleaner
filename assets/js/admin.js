/**
 * DevForge Admin Cleaner
 */
(function($) {
    'use strict';

    // Cleanup buttons
    $(document).on('click', '.wac-action-btn[data-cleanup]', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var type = $btn.data('cleanup');
        var originalText = $btn.html();
        
        if (!confirm('Are you sure you want to run this cleanup?')) {
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Working...');
        
        $.ajax({
            url: wacAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wac_cleanup',
                type: type,
                nonce: wacAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update stats
                    if (response.data.stats) {
                        $('#stat-revisions').text(response.data.stats.revisions);
                        $('#stat-trash').text(response.data.stats.trash);
                        $('#stat-spam').text(response.data.stats.spam);
                        $('#stat-transients').text(response.data.stats.transients);
                    }
                    
                    $('#wac-cleanup-result')
                        .removeClass('error')
                        .addClass('wac-result success')
                        .html('Cleaned ' + response.data.count + ' items.')
                        .show();
                } else {
                    $('#wac-cleanup-result')
                        .removeClass('success')
                        .addClass('wac-result error')
                        .html('Error: ' + (response.data || 'Unknown error'))
                        .show();
                }
            },
            error: function() {
                $('#wac-cleanup-result')
                    .removeClass('success')
                    .addClass('wac-result error')
                    .html('Connection error')
                    .show();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Activity Log
    function loadActivityLog() {
        var $log = $('#wac-activity-log');
        if (!$log.length) return;
        
        $.post(wacAdmin.ajaxUrl, {
            action: 'wac_get_activity_log',
            nonce: wacAdmin.nonce
        }, function(response) {
            if (response.success && response.data && response.data.length) {
                var html = '';
                $.each(response.data, function(i, item) {
                    html += '<div class="wac-log-item">' +
                        '<div class="wac-log-icon"><span class="dashicons dashicons-marker"></span></div>' +
                        '<div class="wac-log-text">' +
                            '<strong>' + escHtml(item.user_name) + '</strong> ' + 
                            item.action + ' ' + item.object_type + ': ' + escHtml(item.object_name) +
                            '<div class="wac-log-meta">' + item.created_at + '</div>' +
                        '</div>' +
                    '</div>';
                });
                $log.html(html);
            } else {
                $log.html('<p style="color:#86868b;text-align:center;padding:20px;">No activity recorded yet</p>');
            }
        });
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Clear log
    $(document).on('click', '#wac-clear-log', function() {
        if (!confirm('Clear all activity logs?')) return;
        
        $.post(wacAdmin.ajaxUrl, {
            action: 'wac_clear_activity_log',
            nonce: wacAdmin.nonce
        }, function() {
            loadActivityLog();
        });
    });

    // Export
    $(document).on('click', '#wac-export-btn', function() {
        $.post(wacAdmin.ajaxUrl, {
            action: 'wac_export_settings',
            nonce: wacAdmin.nonce
        }, function(response) {
            if (response.success) {
                var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'wac-settings.json';
                a.click();
            }
        });
    });

    // Import
    $(document).on('change', '#wac-import-file', function() {
        var file = this.files[0];
        if (!file) return;
        
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var data = JSON.parse(e.target.result);
                $.post(wacAdmin.ajaxUrl, {
                    action: 'wac_import_settings',
                    nonce: wacAdmin.nonce,
                    settings: JSON.stringify(data)
                }, function(response) {
                    if (response.success) {
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

    // Init
    $(function() {
        loadActivityLog();
    });

    // Spinner animation
    $('<style>.dashicons.spin{animation:wac-spin 1s linear infinite}@keyframes wac-spin{100%{transform:rotate(360deg)}}</style>').appendTo('head');

})(jQuery);
